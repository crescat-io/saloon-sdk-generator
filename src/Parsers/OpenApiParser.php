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
use Crescat\SaloonSdkGenerator\Helpers\Utils;
use Crescat\SaloonSdkGenerator\Normalizers\OpenApiNormalizer;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class OpenApiParser implements Parser
{
    protected OpenApi $openApi;

    /** @var string[] */
    protected array $responseSchemaTypes = [];

    public function __construct(OpenApi $openApi)
    {
        $normalizer = new OpenApiNormalizer($openApi);
        $this->openApi = $normalizer->normalize();
    }

    public static function build($content): static
    {
        return new static(
            Str::endsWith($content, '.json')
                ? Reader::readFromJsonFile(fileName: realpath($content), resolveReferences: ReferenceContext::RESOLVE_MODE_INLINE)
                : Reader::readFromYamlFile(fileName: realpath($content), resolveReferences: ReferenceContext::RESOLVE_MODE_INLINE)
        );
    }

    public function parse(): ApiSpecification
    {
        // Parse endpoints before schemas, so that response schemas have already been parsed and
        // marked as responses before we parse every other schema
        $endpoints = $this->parseItems($this->openApi->paths);
        $schemas = $this->parseSchemas($this->openApi->components->schemas ?? []);

        $responses = array_filter($schemas, fn (Schema $schema) => $schema->isResponse);
        $nonResponseSchemas = array_filter($schemas, fn (Schema $schema) => ! $schema->isResponse);

        return new ApiSpecification(
            name: $this->openApi->info->title,
            description: $this->openApi->info->description,
            baseUrl: Arr::first($this->openApi->servers)->url,
            endpoints: $endpoints,
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
            headerParameters: $this->mapParams($operation->parameters, 'header'),
            // TODO: Check if this differs between spec versions
            pathParameters: $pathParams + $this->mapParams($operation->parameters, 'path'),
            bodySchema: $this->parseBody($operation->requestBody),
        );
    }

    /**
     * @param  OpenApiSchema[]  $schemas
     * @return Schema[]
     */
    protected function parseSchemas(array $schemas, ?Schema &$parent = null): array
    {
        $parsedSchemas = [];
        foreach ($schemas as $name => $schema) {
            $_parent = $parent;
            while ($_parent !== null) {
                // We should only ever get circular schema refs from a schema property, so we need
                // to check that the parent's rawName (which would be a property name) matches the
                // name of the schema we're currently iterating over
                if ($_parent->rawName === $name && $_parent->equalsOpenApiSchema($schema)) {
                    continue 2;
                }
                $_parent = $_parent->parent;
            }

            $parsed = $this->parseSchema($schema, $parent, $name);
            if ($parsed) {
                $parsedSchemas[$name] = $parsed;
            }
        }

        return $parsedSchemas;
    }

    protected function parseSchema(
        OpenApiSchema $schema,
        ?Schema &$parent = null,
        ?string $parentPropName = null
    ): Schema {
        // TODO: add support for anyOf and oneOf schemas
        if ($schema->allOf) {
            $parsedSchema = $this->parseAllOfSchema($schema, $parent, $parentPropName);
        } elseif (Type::isScalar($schema->type)) {
            $parsedSchema = new Schema(
                name: $schema->title ?? $parentPropName,
                rawName: $parentPropName,
                type: $this->mapSchemaTypeToPhpType($schema->type, $schema->format),
                description: $schema->description,
                nullable: $schema->required ?? $schema->nullable,
                parent: $parent,
            );
        } elseif ($schema->type === Type::ARRAY) {
            $name = $schema->title;

            $parsedSchema = new Schema(
                name: $name,
                rawName: $parentPropName,
                type: $this->mapSchemaTypeToPhpType($schema->type, $schema->format),
                description: $schema->description,
                nullable: $schema->required ?? $schema->nullable ?? false,
                parent: $parent,
            );

            // Handle scalar array schemas
            // Need to null check $schema->items->type because allOf schemas don't have a type property
            if (SimpleType::isScalar($schema->items->type ?? '')) {
                $parsedSchema->items = new Schema(
                    name: $name,
                    type: $this->mapSchemaTypeToPhpType($schema->items->type, $schema->items->format),
                    description: $schema->description,
                    nullable: $schema->required ?? $schema->nullable ?? false,
                    parent: $parent,
                );
            } else {
                // This is just so that, in the recursive parseSchema call below where we determine the actual
                // item schema, we know the item type of this array
                $tempItemSchema = new Schema(
                    name: $name,
                    type: 'array',
                    description: $schema->description,
                );
                $parsedSchema->items = $tempItemSchema;
                $parsedSchema->items = $this->parseSchema($schema->items, $parsedSchema);
                // TODO: update this once TODO in mapResponses is addressed
                $parsedSchema->isResponse = in_array("{$parsedSchema->items->type}[]", $this->responseSchemaTypes);
            }
        } else {
            $required = $schema->required;
            if (is_null($required)) {
                $required = [];
            }

            // This is a temporary placeholder value that is used until the schema's properties
            // are parsed. It allows child schemas who are passed this as a parent to know
            // which properties its parent has, which is useful in recursive schemas
            $tempProperties = array_combine(
                array_keys($schema->properties),
                array_fill(0, count($schema->properties), null)
            );
            $parsedSchema = new Schema(
                name: $schema->title ?? $parentPropName,
                rawName: $parentPropName,
                nullable: $schema->nullable,
                // Using $schema->title instead of $schema->type since title is the user-defined
                // type name from the schema file
                type: $schema->title ?? 'object',
                description: $schema->description,
                properties: $tempProperties,
                required: $required,
                parent: $parent,
            );

            $parsedProperties = $this->parseSchemas($schema->properties, $parsedSchema);

            // additionalProperties defaults to true according to OpenAPI spec, but it actually isn't
            // usually present. We're ignoring it unless it's explicitly set to a type definition, or
            // the object has no other properties
            if (
                ($schema->type === Type::OBJECT && ! $schema->properties && $schema->additionalProperties)
                || ! is_bool($schema->additionalProperties)
            ) {
                $parsedSchema = $this->addAdditionalProperties($schema, $parsedSchema);
            }

            $parsedSchema->properties = collect($parsedProperties)
                ->sortBy(fn (Schema $schema) => (int) $schema->isNullable())
                ->toArray();

            $parsedSchema->isResponse = in_array($parsedSchema->type, $this->responseSchemaTypes);

            // If there are no properties and this isn't a response schema, we don't need to generate
            // a DTO class for it
            if (count($schema->properties) === 0 && ! $parsedSchema->isResponse) {
                // This handles the case where there is a schema with no properties, but it has additionalProperties
                // set to a type definition
                if (! Utils::isBuiltinType($parsedSchema->type) && $parsedSchema->additionalProperties) {
                    $parsedSchema->items = $parsedSchema->additionalProperties;
                }
                $parsedSchema->type = $this->mapSchemaTypeToPhpType(Type::ARRAY);
            }
        }

        return $parsedSchema;
    }

    protected function parseAllOfSchema(
        OpenApiSchema $schema,
        ?Schema &$parent = null,
        ?string $parentPropName = null
    ): Schema {
        $parsedSchema = new Schema(
            name: $schema->title,
            type: $schema->title ?? 'object',
            description: $schema->description,
            parent: $parent,
            rawName: $parentPropName,
        );

        $allOf = collect($schema->allOf)
            ->mapWithKeys(fn ($s, $i) => ["{$schema->title} composite {$i}" => $s])
            ->toArray();
        $parsedAllOf = $this->parseSchemas($allOf, $parsedSchema);

        // TODO: I feel like there's some neater way to handle allOf schemas using intersection types,
        // but I'm not sure how to do it
        $allProperties = [];
        $allRequirements = [];
        foreach ($parsedAllOf as $s) {
            foreach ($s->properties as $name => $property) {
                // I don't think conflicting property names on allOf schemas are allowed by the OpenAPI
                // spec, so I don't believe we run the risk of overwriting properties here
                $allProperties[$name] = $property;
            }

            $allRequirements = array_merge($allRequirements, $s->required);
        }

        $parsedSchema->isResponse = in_array($parsedSchema->type, $this->responseSchemaTypes);
        $parsedSchema->required = $allRequirements;
        $parsedSchema->properties = collect($allProperties)
            ->sortBy(fn (Schema $schema) => (int) $schema->isNullable())
            ->toArray();

        return $parsedSchema;
    }

    protected function addAdditionalProperties(OpenApiSchema $originalSchema, Schema $parsedSchema): Schema
    {
        $additionalProperties = $originalSchema->additionalProperties;
        $type = SimpleType::MIXED->value;
        if (! is_bool($additionalProperties)) {
            if (
                SimpleType::isScalar($additionalProperties->type)
                || ($additionalProperties->type === Type::OBJECT && ! $additionalProperties->properties)
            ) {
                $type = $this->mapSchemaTypeToPhpType($additionalProperties->type, $additionalProperties->format);
            } else {
                $additionalPropertiesItemSchema = $this->parseSchema($additionalProperties, $parsedSchema);
                $type = $additionalPropertiesItemSchema->type;
            }
        }

        $parsedAdditionalPropsSchema = new Schema(
            name: 'additionalProperties',
            type: $type,
            description: null,
            parent: $parsedSchema,
        );

        $parsedSchema->additionalProperties = $parsedAdditionalPropsSchema;

        return $parsedSchema;
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

        $parsedSchema = $this->parseSchema($mediaType->schema);
        $parsedSchema->contentType = $contentType;
        $parsedSchema->bodyContentType = $contentType;

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
                type: $this->mapSchemaTypeToPhpType($parameter->schema?->type, $parameter->schema?->format),
                nullable: $parameter->required == false,
                name: $parameter->name,
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
                if (! $response || ! $response->content || $httpCode === 204) {
                    return [$httpCode => []];
                } elseif ($response instanceof OpenApiReference) {
                    $response = $response->resolve();
                }

                $responseSchemas = collect($response->content)
                    ->mapWithKeys(function (MediaType $content, string $contentType) {
                        $schema = $content->schema;
                        if ($schema instanceof OpenApiReference) {
                            $schema = $content->schema->resolve();
                        }

                        $parsedSchema = $this->parseSchema($schema);
                        $parsedSchema->contentType = $contentType;
                        $parsedSchema->isResponse = true;

                        if (! in_array($parsedSchema->type, $this->responseSchemaTypes)) {
                            $savedType = $parsedSchema->type;
                            // Without this, we end up with "array" in the response types list.
                            // TODO: This is a brittle solution and doesn't account for scalar arrays
                            if ($parsedSchema->type === SimpleType::ARRAY->value) {
                                $savedType = "{$parsedSchema->items->type}[]";
                            }
                            $this->responseSchemaTypes[] = $savedType;
                        }

                        return [$contentType => $parsedSchema];
                    });

                return [$httpCode => $responseSchemas];
            })
            ->toArray();
    }

    protected function mapSchemaTypeToPhpType(string $type, ?string $format = null): string
    {
        if ($type === Type::STRING && $format) {
            return match ($format) {
                'date-time', 'date' => 'DateTime',
                default => 'string',
            };
        }

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
