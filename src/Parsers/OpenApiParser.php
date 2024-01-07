<?php

declare(strict_types=1);

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
use cebe\openapi\spec\RequestBody as OpenApiRequestBody;
use cebe\openapi\spec\Response as OpenApiResponse;
use cebe\openapi\spec\Responses as OpenApiResponses;
use cebe\openapi\spec\Schema as OpenApiSchema;
use cebe\openapi\spec\Type;
use Crescat\SaloonSdkGenerator\Contracts\Parser;
use Crescat\SaloonSdkGenerator\Data\Generator\ApiSpecification;
use Crescat\SaloonSdkGenerator\Data\Generator\Endpoint;
use Crescat\SaloonSdkGenerator\Data\Generator\Method;
use Crescat\SaloonSdkGenerator\Data\Generator\Parameter;
use Crescat\SaloonSdkGenerator\Data\Generator\Schema;
use Crescat\SaloonSdkGenerator\Enums\SimpleType;
use Crescat\SaloonSdkGenerator\Helpers\NameHelper;
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
        // Schema preprocessing is a prerequisite for parsing schemas, request bodies, and responses. The preprocessed
        // schemas are used inside of $this->parseEndpoint()
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
            bodySchema: $this->parseBody($operation->requestBody),
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
            $safeName = NameHelper::normalize($name);
            if (array_key_exists($safeName, $preprocessedSchemas)) {
                continue;
            }

            if ($schema instanceof OpenApiReference) {
                $schema = $schema->resolve();
            }

            $schema->title = $safeName;
            $preprocessedSchemas[$safeName] = $schema;
        }

        return $preprocessedSchemas;
    }

    /**
     * @param  OpenApiSchema[]|OpenApiReference[]  $schemas
     * @return Schema[]
     */
    protected function parseSchemas(array $schemas, ?Schema &$parent = null): array
    {
        $parsedSchemas = [];
        foreach ($schemas as $name => $schema) {
            $safeName = NameHelper::normalize($name);
            $parsed = $this->parseSchema($schema, $parent, $safeName);
            if ($parsed) {
                $parsedSchemas[$safeName] = $parsed;
            }
        }

        return $parsedSchemas;
    }

    protected function parseSchema(
        OpenApiSchema $schema,
        ?Schema &$parent = null,
        ?string $parentPropName = null
    ): Schema {
        if (Type::isScalar($schema->type)) {
            return new Schema(
                name: $schema->title,
                type: $this->mapSchemaTypeToPhpType($schema->type),
                description: $schema->description,
                nullable: $schema->nullable,
                parent: $parent,
                parentPropName: $parentPropName,
            );
        } elseif ($schema->type === Type::ARRAY) {
            $name = $schema->title;
            if (str_contains($name, 'List')) {
                $noList = str_replace('List', '', $name);
                $pluralized = Str::plural($noList);
                $name = $pluralized;
            }

            $parsedSchema = new Schema(
                name: $name,
                type: $this->mapSchemaTypeToPhpType($schema->type),
                description: $schema->description,
                nullable: $schema->nullable,
                parent: $parent,
            );

            // Handle scalar array schemas
            if (Type::isScalar($schema->items->type)) {
                $parsedSchema->items = new Schema(
                    name: $name,
                    type: $this->mapSchemaTypeToPhpType($schema->items->type),
                    description: $schema->description,
                    nullable: $schema->nullable,
                    parent: $parent,
                );
            } else {
                $parsedSchema->items = $this->parseSchema($schema->items, $parsedSchema);
            }

            return $parsedSchema;
        } else {
            $properties = $schema->properties;
            $preprocessedProperties = $this->preprocessSchemas($properties);

            $safeRequired = is_array($schema->required)
                ? array_map(fn ($prop) => NameHelper::normalize($prop), $schema->required)
                : $schema->required;

            $parsedSchema = new Schema(
                name: $schema->title,
                nullable: $schema->nullable,
                type: $schema->title ?? 'object',
                description: $schema->description,
                required: $safeRequired,
                parent: $parent,
                parentPropName: $parentPropName,
            );
            $parsedProperties = $this->parseSchemas($preprocessedProperties, $parsedSchema);

            $additionalProperties = $schema->additionalProperties;
            if ($additionalProperties) {
                $type = SimpleType::MIXED->value;
                if (! is_bool($additionalProperties)) {
                    if ($additionalProperties instanceof OpenApiReference) {
                        $additionalProperties = $additionalProperties->resolve();
                    }
                    if (SimpleType::tryFrom($additionalProperties->type) || $additionalProperties->type === Type::OBJECT) {
                        $type = $this->mapSchemaTypeToPhpType($additionalProperties->type);
                    } else {
                        $additionalPropertiesItemType = $this->parseSchema($additionalProperties, $parsedSchema);
                        $type = $additionalPropertiesItemType->type;
                    }
                }

                $parsedAdditionalPropsSchema = new Schema(
                    name: 'additionalProperties',
                    type: $type,
                    description: null,
                    parent: $parsedSchema,
                );

                $parsedSchema->additionalProperties = $parsedAdditionalPropsSchema;

                // If there are no other properties, then this schema is just an array of additional properties,
                // and shouldn't be treated as an explicitly defined object type
                if (count($schema->properties) === 0) {
                    $parsedSchema->type = $this->mapSchemaTypeToPhpType(Type::ARRAY);
                }
            }

            $parsedSchema->properties = collect($parsedProperties)
                ->sortBy(fn (Schema $schema) => (int) $schema->isNullable())
                ->toArray();

            return $parsedSchema;
        }
    }

    /**
     * @return ?Schema
     */
    protected function parseBody(OpenApiRequestBody|OpenApiReference|null $body): ?Schema
    {
        if (! $body) {
            return null;
        }

        if ($body instanceof OpenApiReference) {
            $body = $body->resolve();
        }

        if (count($body->content) === 0) {
            return null;
        }

        // TODO: Support multiple request content types. I think Saloon would need to be
        // updated to support this, too
        $contentType = array_key_first($body->content);
        $mediaType = $body->content[$contentType];

        if ($mediaType->schema instanceof OpenApiReference) {
            $mediaType->schema = $mediaType->schema->resolve();
        }

        $parsedSchema = $this->schemas[$mediaType->schema->title];

        return $parsedSchema;
    }

    /**
     * @param  OpenApiParameter[]  $parameters
     * @return Parameter[]
     */
    protected function mapParams(array $parameters, string $in): array
    {
        return collect($parameters)
            ->filter(fn (OpenApiParameter $parameter) => $parameter->in == $in)
            ->map(fn (OpenApiParameter $parameter) => new Parameter(
                type: $this->mapSchemaTypeToPhpType($parameter->schema?->type),
                nullable: $parameter->required == false,
                name: NameHelper::normalize($parameter->name),
                description: $parameter->description,
            ))
            ->sortBy(fn (Parameter $parameter) => (int) $parameter->isNullable())
            ->all();
    }

    /**
     * @return Schema[]
     */
    protected function mapResponses(OpenApiResponses $responses): array
    {
        return collect($responses->getResponses())
            ->mapWithKeys(function (OpenApiResponse|OpenApiReference|null $response, int $httpCode) {
                if (! $response || $httpCode === 204) {
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
            Type::NUMBER => 'float',
            Type::STRING => 'string',
            Type::BOOLEAN => 'bool',
            Type::ARRAY, Type::OBJECT => 'array',
            Type::OBJECT => $type,  // For schema references
            default => 'mixed',
        };
    }
}
