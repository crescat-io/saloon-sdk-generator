<?php

namespace Crescat\SaloonSdkGenerator\Parsers;

use cebe\openapi\Reader;
use cebe\openapi\ReferenceContext;
use cebe\openapi\spec\Components;
use cebe\openapi\spec\OpenApi;
use cebe\openapi\spec\Operation;
use cebe\openapi\spec\Parameter as OpenApiParameter;
use cebe\openapi\spec\PathItem;
use cebe\openapi\spec\Paths;
use cebe\openapi\spec\Reference;
use cebe\openapi\spec\RequestBody;
use cebe\openapi\spec\SecurityRequirement;
use cebe\openapi\spec\Server;
use cebe\openapi\spec\Type;
use Crescat\SaloonSdkGenerator\Contracts\Parser;
use Crescat\SaloonSdkGenerator\Data\Generator\ApiKeyLocation;
use Crescat\SaloonSdkGenerator\Data\Generator\ApiSpecification;
use Crescat\SaloonSdkGenerator\Data\Generator\BaseUrl;
use Crescat\SaloonSdkGenerator\Data\Generator\Endpoint;
use Crescat\SaloonSdkGenerator\Data\Generator\Method;
use Crescat\SaloonSdkGenerator\Data\Generator\Parameter;
use Crescat\SaloonSdkGenerator\Data\Generator\SecurityScheme;
use Crescat\SaloonSdkGenerator\Data\Generator\SecuritySchemeType;
use Crescat\SaloonSdkGenerator\Data\Generator\ServerParameter;
use Illuminate\Support\Str;
use Throwable;

class OpenApiParser implements Parser
{
    public function __construct(protected OpenApi $openApi) {}

    public static function build($content): self
    {
        return new self(
            Str::endsWith($content, '.json')
                ? Reader::readFromJsonFile(fileName: realpath($content), resolveReferences: ReferenceContext::RESOLVE_MODE_INLINE)
                : Reader::readFromYamlFile(fileName: realpath($content), resolveReferences: ReferenceContext::RESOLVE_MODE_INLINE)
        );
    }

    public function parse(): ApiSpecification
    {

        return new ApiSpecification(
            name: $this->openApi->info->title,
            description: $this->openApi->info->description,
            baseUrl: $this->parseBaseUrl($this->openApi->servers),
            securityRequirements: $this->openApi->security !== null ? $this->parseSecurityRequirements($this->openApi->security->getSerializableData()) : [],
            components: $this->parseComponents($this->openApi->components),
            endpoints: $this->parseItems($this->openApi->paths)
        );
    }

    /**
     * @param  Server[]  $servers
     */
    protected function parseBaseUrl(?array $servers): BaseUrl
    {
        /** @var Server $server */
        $server = array_shift($servers);
        if (is_null($server->variables)) {
            return new BaseUrl('');
        }

        $parameters = [];
        foreach ($server->variables as $name => $variable) {
            $parameters[] = new ServerParameter($name, $variable->default, $variable->description);
        }

        return new BaseUrl($server->url, $parameters);
    }

    /**
     * @return array|Endpoint[]
     */
    protected function parseItems(?Paths $items): array
    {
        if (! $items) {
            return [];
        }

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

    /**
     * @return \Crescat\SaloonSdkGenerator\Data\Generator\SecurityRequirement[]
     */
    protected function parseSecurityRequirements(array $security): array
    {
        $securityRequirements = [];

        foreach ($security as $key => $securityOption) {
            // Handle case where it's already an array (from SecurityRequirements->getSerializableData())
            if (is_array($securityOption)) {
                foreach ($securityOption as $name => $scopes) {
                    $securityRequirements[] = new \Crescat\SaloonSdkGenerator\Data\Generator\SecurityRequirement(
                        $name,
                        $scopes
                    );
                }

                continue;
            }

            // Handle case where it's a SecurityRequirement object
            $data = $securityOption->getSerializableData();
            if (gettype($data) !== 'object') {
                continue;
            }

            $securityProperties = get_object_vars($data);

            foreach ($securityProperties as $name => $scopes) {
                $securityRequirements[] = new \Crescat\SaloonSdkGenerator\Data\Generator\SecurityRequirement(
                    $name,
                    $scopes
                );
            }
        }

        return $securityRequirements;
    }

    protected function parseComponents(?Components $components): \Crescat\SaloonSdkGenerator\Data\Generator\Components
    {
        if (! $components) {
            return new \Crescat\SaloonSdkGenerator\Data\Generator\Components;
        }

        $securitySchemes = [];
        foreach ($components->securitySchemes as $securityScheme) {

            $securitySchemes[] = new SecurityScheme(
                type: SecuritySchemeType::tryFrom($securityScheme->type),
                name: $securityScheme->name,
                in: ApiKeyLocation::tryFrom($securityScheme->in ?? ''),
                scheme: $securityScheme->scheme,
                bearerFormat: $securityScheme->bearerFormat,
                description: $securityScheme->description,
                flows: $securityScheme->flows,
                openIdConnectUrl: $securityScheme->openIdConnectUrl
            );
        }

        return new \Crescat\SaloonSdkGenerator\Data\Generator\Components(
            schemas: $components->schemas,
            securitySchemes: $securitySchemes
        );
    }

    protected function parseEndpoint(Operation $operation, $pathParams, string $path, string $method): ?Endpoint
    {

        return new Endpoint(
            name: trim($operation->operationId ?: $operation->summary ?: ''),
            method: Method::parse($method),
            pathSegments: Str::of($path)->replace('{', ':')->remove('}')->trim('/')->explode('/')->toArray(),
            collection: $operation->tags[0] ?? null, // In the real-world, people USUALLY only use one tag...
            response: $this->parseResponseSchema($operation),
            description: $operation->description,
            queryParameters: $this->mapParams($operation->parameters ?? [], 'query'),
            // TODO: Check if this differs between spec versions
            pathParameters: $pathParams + $this->mapParams($operation->parameters ?? [], 'path'),
            bodyParameters: [], // TODO: implement "definition" parsing
            headerParameters: $this->mapParams($operation->parameters ?? [], 'header'),
        );
    }

    /**
     * Extract the response schema name from the operation's responses.
     *
     * @return array{schema: string}|null
     */
    protected function parseResponseSchema(Operation $operation): ?array
    {
        if (! $operation->responses) {
            return null;
        }

        // Look for success responses (200, 201, 204, or 'default')
        $successCodes = ['200', '201', '204', 'default'];

        foreach ($successCodes as $code) {
            $response = $operation->responses->getResponse($code);

            if (! $response || $response instanceof Reference) {
                continue;
            }

            // Get content from the response
            $content = $response->content['application/json'] ?? null;

            if (! $content || ! $content->schema) {
                continue;
            }

            $schema = $content->schema;

            // If schema is a Reference, extract the schema name from the $ref
            if ($schema instanceof Reference) {
                $ref = $schema->getReference();
                // e.g., "#/components/schemas/AccountDto" -> "AccountDto"
                if (Str::startsWith($ref, '#/components/schemas/')) {
                    return ['schema' => Str::afterLast($ref, '/')];
                }
            }

            // Handle array responses: { "type": "array", "items": { "$ref": "..." } }
            if (isset($schema->type) && $schema->type === 'array' && isset($schema->items)) {
                $items = $schema->items;
                if ($items instanceof Reference) {
                    $ref = $items->getReference();
                    if (Str::startsWith($ref, '#/components/schemas/')) {
                        return ['schema' => Str::afterLast($ref, '/')];
                    }
                }
            }

            // If schema has a title, use that
            if (isset($schema->title)) {
                return ['schema' => $schema->title];
            }
        }

        return null;
    }

    protected function parseRequestBody(RequestBody|Reference|null $requestBody): ?array
    {

        if (! $requestBody) {
            return [];
        }

        $bodyParameters = [];

        // Assume that the requestBody content is of type 'application/json'
        $content = $requestBody->content['application/json'] ?? null;

        try {

            if ($requestBody instanceof Reference) {
                $requestBody = $requestBody->resolveReferences();
            }

            // Resolve schema if it's a reference
            $schema = $content?->schema;
            if ($schema instanceof Reference) {
                try {
                    $schema = $schema->resolve();
                } catch (Throwable) {
                    $schema = null;
                }
            }

            if ($content && $schema?->type != null) {
                foreach ($schema->properties as $name => $property) {
                    // Resolve property if it's a reference
                    if ($property instanceof Reference) {
                        try {
                            $property = $property->resolve();
                        } catch (Throwable) {
                            continue;
                        }
                    }

                    $bodyParameters[] = new Parameter(
                        type: $this->mapSchemaTypeToPhpType($property->type ?? null),
                        nullable: $property->nullable ?? false,
                        name: $name,
                        description: $property->description ?? ''
                    );
                }
            }
        } catch (Throwable) {
            return [];
        }

        return $bodyParameters;
    }

    /**
     * @param  array  $parameters  Array of OpenApiParameter or Reference objects
     * @return Parameter[] array
     */
    protected function mapParams(array $parameters, string $in): array
    {
        return collect($parameters)
            ->map(function ($parameter) {
                // Resolve Reference objects to their actual Parameter objects
                if ($parameter instanceof Reference) {
                    // When using RESOLVE_MODE_INLINE, we need to manually resolve the reference
                    $refPath = $parameter->getReference();

                    // Parse the reference path (e.g., "#/components/parameters/PathAlbumId")
                    if (str_starts_with($refPath, '#/components/parameters/')) {
                        $paramName = str_replace('#/components/parameters/', '', $refPath);

                        // Check if the parameter exists in components
                        if (isset($this->openApi->components->parameters[$paramName])) {
                            $resolvedParam = $this->openApi->components->parameters[$paramName];

                            // The resolved parameter might itself be a Reference in some cases
                            if ($resolvedParam instanceof Reference) {
                                return null;
                            }

                            return $resolvedParam;
                        }
                    }

                    return null;
                }

                return $parameter;
            })
            ->filter() // Remove any nulls from failed resolutions
            ->whereInstanceOf(OpenApiParameter::class)
            ->filter(fn (OpenApiParameter $parameter) => $parameter->in == $in)
            ->map(function (OpenApiParameter $parameter) {
                // Resolve schema if it's a reference
                $schema = $parameter->schema;
                if ($schema instanceof Reference) {
                    try {
                        $schema = $schema->resolve();
                    } catch (Throwable) {
                        $schema = null;
                    }
                }

                return new Parameter(
                    type: $this->mapSchemaTypeToPhpType($schema?->type ?? null),
                    nullable: $parameter->required == false,
                    name: $parameter->name,
                    description: $parameter->description,
                );
            })
            ->values() // Reset array keys
            ->all();
    }

    protected function mapSchemaTypeToPhpType($type): string
    {
        return match ($type) {
            Type::INTEGER => 'int',
            Type::NUMBER => 'float|int', // TODO: is "number" always a float in openapi specs?
            Type::STRING => 'string',
            Type::BOOLEAN => 'bool',
            Type::OBJECT, Type::ARRAY => 'array',
            default => 'mixed',
        };
    }
}
