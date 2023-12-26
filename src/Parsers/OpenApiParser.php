<?php

namespace Crescat\SaloonSdkGenerator\Parsers;

use cebe\openapi\Reader;
use cebe\openapi\ReferenceContext;
use cebe\openapi\spec\MediaType;
use cebe\openapi\spec\OpenApi;
use cebe\openapi\spec\Operation;
use cebe\openapi\spec\Parameter as OpenApiParameter;
use cebe\openapi\spec\PathItem;
use cebe\openapi\spec\Paths;
use cebe\openapi\spec\Reference as OpenApiReference;
use cebe\openapi\spec\Response as OpenApiResponse;
use cebe\openapi\spec\Responses;
use cebe\openapi\spec\Schema as OpenApiSchema;
use cebe\openapi\spec\Type;
use Crescat\SaloonSdkGenerator\Contracts\Parser;
use Crescat\SaloonSdkGenerator\Data\Generator\ApiSpecification;
use Crescat\SaloonSdkGenerator\Data\Generator\Endpoint;
use Crescat\SaloonSdkGenerator\Data\Generator\Method;
use Crescat\SaloonSdkGenerator\Data\Generator\Parameter;
use Crescat\SaloonSdkGenerator\Data\Generator\Schema;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class OpenApiParser implements Parser
{
    /** @var Schema[] */
    protected array $schemas;

    /** @var Endpoint[] */
    protected array $endpoints;

    public function __construct(protected OpenApi $openApi)
    {
    }

    public static function build($content): static
    {
        return new static(
            Str::endsWith($content, '.json')
                ? Reader::readFromJsonFile(fileName: realpath($content), resolveReferences: ReferenceContext::RESOLVE_MODE_ALL)
                : Reader::readFromYamlFile(fileName: realpath($content), resolveReferences: ReferenceContext::RESOLVE_MODE_ALL)
        );
    }

    public function parse(): ApiSpecification
    {
        // Schema preprocessing is a prerequisite for parsing schemas AND responses (inside of endpoints)
        $this->preprocessSchemas($this->openApi->components->schemas);
        // Parse schemas before endpoints, because endpoint responses often reference schemas
        $this->schemas = $this->parseSchemas($this->openApi->components->schemas ?? []);
        $this->endpoints = $this->parseItems($this->openApi->paths);

        $responses = array_filter($this->schemas, fn (Schema $schema) => $schema->isResponse);
        $nonResponseSchemas = array_filter($this->schemas, fn (Schema $schema) => ! $schema->isResponse);

        return new ApiSpecification(
            name: $this->openApi->info->title,
            description: $this->openApi->info->description,
            baseUrl: Arr::first($this->openApi->servers)->url,
            endpoints: $this->endpoints,
            schemas: $nonResponseSchemas,
            responses: $responses,
        );
    }

    /**
     * @return array|Endpoint[]
     */
    protected function parseItems(Paths $items): array
    {
        $requests = [];

        foreach ($items as $path => $item) {
            if ($item instanceof PathItem) {
                foreach ($item->getOperations() as $method => $operation) {
                    // TODO: variables for the path
                    $requests[] = $this->parseEndpoint($operation, $this->mapParams($item->parameters, 'path'), $path, $method);
                }
            }
        }

        return $requests;
    }

    protected function parseEndpoint(Operation $operation, $pathParams, string $path, string $method): ?Endpoint
    {
        return new Endpoint(
            name: trim($operation->operationId ?: $operation->summary ?: ''),
            method: Method::parse($method),
            pathSegments: Str::of($path)->replace('{', ':')->remove('}')->trim('/')->explode('/')->toArray(),
            collection: $operation->tags[0] ?? null, // In the real-world, people USUALLY only use one tag...
            responses: $this->mapResponses($operation->responses),
            description: $operation->description,
            queryParameters: $this->mapParams($operation->parameters, 'query'),
            // TODO: Check if this differs between spec versions
            pathParameters: $pathParams + $this->mapParams($operation->parameters, 'path'),
            bodyParameters: [], // TODO: implement "definition" parsing
        );
    }

    /**
     * @param  OpenApiSchema[]  $schemas
     * @return OpenApiSchema[]
     */
    protected function preprocessSchemas(array $schemas): array
    {
        $preprocessedSchemas = [];
        foreach ($schemas as $name => $schema) {
            if (array_key_exists($name, $preprocessedSchemas)) {
                continue;
            }

            if ($schema instanceof OpenApiReference) {
                $schema = $schema->resolve();
            }

            if (! $schema->title) {
                $schema->title = $name;
            }

            $preprocessedSchemas[$name] = $schema;
        }

        return $preprocessedSchemas;
    }

    /**
     * @param  OpenApiSchema[]|OpenApiReference[]  $schemas
     * @param  Parameter[]  $parsedSchemas
     * @return Schema[]
     */
    protected function parseSchemas(array $schemas): array
    {
        foreach ($schemas as $name => $schema) {
            $parsed = $this->parseSchema($schema);
            if ($parsed) {
                $parsedSchemas[$name] = $parsed;
            }
        }

        return $parsedSchemas;
    }

    protected function parseSchema(OpenApiSchema $schema): Schema
    {
        if (Type::isScalar($schema->type)) {
            return new Schema(
                name: $schema->title,
                type: $this->mapSchemaTypeToPhpType($schema->type),
                description: $schema->description,
                nullable: $schema->nullable,
            );
        } elseif ($schema->type === Type::ARRAY) {
            $name = $schema->title;
            if (str_contains($name, 'List')) {
                $noList = str_replace('List', '', $name);
                $pluralized = Str::plural($noList);
                $name = $pluralized;
            }

            return new Schema(
                name: $name,
                type: $this->mapSchemaTypeToPhpType($schema->type),
                description: $schema->description,
                nullable: $schema->nullable,
                items: $this->parseSchema($schema->items, $schema->items->title),
            );
        } else {
            $preprocessedProperties = $this->preprocessSchemas($schema->properties);

            return new Schema(
                name: $schema->title,
                nullable: $schema->nullable,
                type: $schema->title,
                description: $schema->description,
                properties: $this->parseSchemas($preprocessedProperties),
                required: $schema->required ?? [],
            );
        }
    }

    /**
     * @param  OpenApiParameter[]  $parameters
     * @return Parameter[] array
     */
    protected function mapParams(array $parameters, string $in): array
    {
        return collect($parameters)
            ->filter(fn (OpenApiParameter $parameter) => $parameter->in == $in)
            ->map(fn (OpenApiParameter $parameter) => new Parameter(
                type: $this->mapSchemaTypeToPhpType($parameter->schema?->type),
                nullable: $parameter->required == false,
                name: $parameter->name,
                description: $parameter->description,
            ))
            ->all();
    }

    /**
     * @return Schema[]
     */
    protected function mapResponses(Responses $responses): array
    {
        return collect($responses->getResponses())
            ->mapWithKeys(function (OpenApiResponse|OpenApiReference|null $response, int $httpCode) {
                if (! $response) {
                    return [];
                } elseif ($response instanceof OpenApiReference) {
                    $response = $response->resolve();
                }

                $responseSchemas = collect($response->content)
                    ->mapWithKeys(function (MediaType $content, string $contentType) {
                        $schema = $content->schema;
                        if ($schema instanceof OpenApiReference) {
                            $schema = $content->schema->resolve();
                        }
                        $parsedSchema = $this->schemas[$schema->title];
                        $parsedSchema->isResponse = true;

                        return [$contentType => $parsedSchema];
                    });

                return [$httpCode => $responseSchemas];
            })
            ->toArray();
    }

    protected function mapSchemaTypeToPhpType($type): string
    {
        return match ($type) {
            Type::INTEGER => 'int',
            Type::NUMBER => 'float|int', // TODO: is "number" always a float in openapi specs?
            Type::STRING => 'string',
            Type::BOOLEAN => 'bool',
            Type::ARRAY => 'array',
            Type::OBJECT => $type,  // For schema references
            default => 'mixed',
        };
    }
}
