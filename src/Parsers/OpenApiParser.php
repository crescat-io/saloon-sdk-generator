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
use cebe\openapi\spec\SecurityRequirement;
use cebe\openapi\spec\Server;
use cebe\openapi\spec\Type;
use Crescat\SaloonSdkGenerator\Contracts\Parser;
use Crescat\SaloonSdkGenerator\Data\Generator\ApiSpecification;
use Crescat\SaloonSdkGenerator\Data\Generator\BaseUrl;
use Crescat\SaloonSdkGenerator\Data\Generator\Endpoint;
use Crescat\SaloonSdkGenerator\Data\Generator\Method;
use Crescat\SaloonSdkGenerator\Data\Generator\Parameter;
use Crescat\SaloonSdkGenerator\Data\Generator\SecurityScheme;
use Crescat\SaloonSdkGenerator\Data\Generator\ServerParameter;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class OpenApiParser implements Parser
{
    public function __construct(protected OpenApi $openApi)
    {
    }

    public static function build($content): self
    {
        return new self(
            Str::endsWith($content, '.json')
                ? Reader::readFromJsonFile(fileName: realpath($content), resolveReferences: ReferenceContext::RESOLVE_MODE_ALL)
                : Reader::readFromYamlFile(fileName: realpath($content), resolveReferences: ReferenceContext::RESOLVE_MODE_ALL)
        );
    }

    public function parse(): ApiSpecification
    {
        return new ApiSpecification(
            name: $this->openApi->info->title,
            description: $this->openApi->info->description,
            baseUrl: $this->parseBaseUrl($this->openApi->servers),
            securityRequirements: $this->parseSecurityRequirements($this->openApi->security),
            components: $this->parseComponents($this->openApi->components),
            endpoints: $this->parseItems($this->openApi->paths)
        );
    }


    /**
     * @param Server[] $servers
     * @return BaseUrl
     */
    protected function parseBaseUrl(array $servers): BaseUrl
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

    /**
     * @param SecurityRequirement[] $security
     * @return \Crescat\SaloonSdkGenerator\Data\Generator\SecurityRequirement[]
     */
    protected function parseSecurityRequirements(array $security): array
    {
        $securityRequirements = [];

        foreach ($security as $key => $securityOption) {
            $data = $securityOption->getSerializableData();
            if (gettype($data) !== 'object') continue;

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

    /**
     * @param \cebe\openapi\spec\Components $components
     * @return \Crescat\SaloonSdkGenerator\Data\Generator\Components
     */
    protected function parseComponents(Components $components): \Crescat\SaloonSdkGenerator\Data\Generator\Components
    {
        $securitySchemes = [];
        foreach ($components->securitySchemes as $securityScheme) {

            $securitySchemes[] = new SecurityScheme(
                $securityScheme->type,
                $securityScheme->name,
                $securityScheme->in,
                $securityScheme->scheme,
                $securityScheme->description,
                $securityScheme->bearerFormat,
                $securityScheme->flows,
                $securityScheme->openIdConnectUrl
            );
        }

        return new \Crescat\SaloonSdkGenerator\Data\Generator\Components(
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
            response: null, // TODO: implement "definition" parsing
            description: $operation->description,
            queryParameters: $this->mapParams($operation->parameters, 'query'),
            // TODO: Check if this differs between spec versions
            pathParameters: $pathParams + $this->mapParams($operation->parameters, 'path'),
            bodyParameters: [], // TODO: implement "definition" parsing
        );
    }

    /**
     * @param OpenApiParameter[] $parameters
     * @return Parameter[] array
     */
    protected function mapParams(array $parameters, string $in): array
    {
        return collect($parameters)
            ->filter(fn(OpenApiParameter $parameter) => $parameter->in == $in)
            ->map(fn(OpenApiParameter $parameter) => new Parameter(
                type: $this->mapSchemaTypeToPhpType($parameter->schema?->type),
                nullable: $parameter->required == false,
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
