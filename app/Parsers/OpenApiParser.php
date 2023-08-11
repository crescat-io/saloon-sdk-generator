<?php

namespace App\Parsers;

use App\Data\Generator\Endpoint;
use App\Data\Generator\Parameter;
use cebe\openapi\spec\OpenApi;
use cebe\openapi\spec\Operation;
use cebe\openapi\spec\Parameter as OpenApiParameter;
use cebe\openapi\spec\PathItem;
use cebe\openapi\spec\Paths;
use cebe\openapi\spec\Type;

class OpenApiParser implements Parser
{
    public function __construct(protected OpenApi $openApi)
    {
    }

    /**
     * @return array|Endpoint[]
     */
    public function parse(): array
    {
        return $this->parseItems($this->openApi->paths);
    }

    /**
     * @return array|Endpoint[]
     */
    public function parseItems(Paths $items): array
    {
        $requests = [];

        foreach ($items as $path => $item) {

            if ($item instanceof PathItem) {
                foreach ($item->getOperations() as $method => $operation) {
                    $requests[] = $this->parseEndpoint($operation, $path, $method);
                }
            }
        }

        return $requests;
    }

    public function parseEndpoint(Operation $operation, string $path, string $method): ?Endpoint
    {
        return new Endpoint(
            name: $operation->summary,
            method: $method,
            pathSegments: explode('/', trim($path, '/')),
            collection: $operation->tags[0] ?? null, // In the real-world, people USUALLY only use one tag...
            response: null, // TODO: implement "definition" parsing
            description: $operation->description,
            queryParameters: $this->mapParams($operation->parameters, 'query'),
            pathParameters: $this->mapParams($operation->parameters, 'path'),
            bodyParameters: null, // TODO: implement "definition" parsing
        );
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
                type: $this->mapSchemaTypeToPhpType($parameter->schema->type),
                name: $parameter->name,
                description: $parameter->description,
            ))
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
