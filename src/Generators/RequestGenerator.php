<?php

declare(strict_types=1);

namespace Crescat\SaloonSdkGenerator\Generators;

use Crescat\SaloonSdkGenerator\Data\Generator\ApiSpecification;
use Crescat\SaloonSdkGenerator\Data\Generator\Endpoint;
use Crescat\SaloonSdkGenerator\Data\Generator\Parameter;
use Crescat\SaloonSdkGenerator\Data\Generator\Schema;
use Crescat\SaloonSdkGenerator\EmptyResponse;
use Crescat\SaloonSdkGenerator\Generator;
use Crescat\SaloonSdkGenerator\Helpers\MethodGeneratorHelper;
use Crescat\SaloonSdkGenerator\Helpers\NameHelper;
use Crescat\SaloonSdkGenerator\Helpers\Utils;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Helpers;
use Nette\PhpGenerator\Literal;
use Nette\PhpGenerator\PhpFile;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method as SaloonHttpMethod;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Traits\Body\HasJsonBody;

class RequestGenerator extends Generator
{
    public function generate(ApiSpecification $specification): PhpFile|array
    {
        $classes = [];

        foreach ($specification->endpoints as $endpoint) {
            $classes[] = $this->generateRequestClass($endpoint);
        }

        return $classes;
    }

    protected function generateRequestClass(Endpoint $endpoint): PhpFile
    {
        $resourceName = NameHelper::resourceClassName($endpoint->collection ?: $this->config->fallbackResourceName);
        $className = NameHelper::requestClassName($endpoint->name);

        [$classFile, $namespace, $classType] = $this->makeClass(
            $className, [$this->config->requestNamespaceSuffix, $resourceName]
        );

        $classType->setExtends(Request::class)
            ->setComment($endpoint->name)
            ->addComment('')
            ->addComment(Utils::wrapLongLines($endpoint->description ?? ''));

        // TODO: We assume JSON body if post/patch, make these assumptions configurable in the future.
        if ($endpoint->method->isPost() || $endpoint->method->isPatch()) {
            $classType
                ->addImplement(HasBody::class)
                ->addTrait(HasJsonBody::class);

            $namespace
                ->addUse(HasBody::class)
                ->addUse(HasJsonBody::class);
        }

        $classType->addProperty('method')
            ->setProtected()
            ->setType(SaloonHttpMethod::class)
            ->setValue(
                new Literal(
                    sprintf('Method::%s', $endpoint->method->value)
                )
            );

        $this->generateConstructor($endpoint, $classType);

        $classType->addMethod('resolveEndpoint')
            ->setPublic()
            ->setReturnType('string')
            ->addBody(
                collect($endpoint->pathSegments)
                    ->map(fn ($segment) => Str::startsWith($segment, ':')
                        ? new Literal(sprintf('{$this->%s}', NameHelper::safeVariableName($segment)))
                        : $segment
                    )
                    ->pipe(fn (Collection $segments) => new Literal(sprintf('return "/%s";', $segments->implode('/'))))
            );

        $responseSuffix = NameHelper::optionalNamespaceSuffix($this->config->responseNamespaceSuffix);
        $responseNamespace = "{$this->config->namespace}{$responseSuffix}";

        $codesByResponseType = collect($endpoint->responses)
            // TODO: We assume JSON is the only response content type for each HTTP status code.
            // We should support multiple response types in the future
            ->mapWithKeys(function (array $response, int $httpCode) use ($namespace, $responseNamespace) {
                if (count($response) === 0) {
                    $cls = EmptyResponse::class;
                } else {
                    $className = NameHelper::responseClassName($response[array_key_first($response)]->name);
                    $cls = "{$responseNamespace}\\{$className}";
                }
                $namespace->addUse($cls);

                return [$httpCode => $cls];
            })
            ->reduce(function (Collection $carry, string $className, int $httpCode) {
                $carry->put(
                    $className,
                    [...$carry->get($className, []), $httpCode]
                );

                return $carry;
            }, collect());

        $namespace
            ->addUse(Exception::class)
            ->addUse(Response::class);

        $createDtoMethod = $classType->addMethod('createDtoFromResponse')
            ->setPublic()
            ->setReturnType($codesByResponseType->keys()->implode('|'))
            ->addBody('$status = $response->status();')
            ->addBody('$responseCls = match ($status) {')
            ->addBody(
                $codesByResponseType
                    ->map(fn (array $codes, string $className) => sprintf(
                        '    %s => %s::class,',
                        implode(', ', $codes), Helpers::extractShortName($className)
                    ))
                    ->values()
                    ->implode("\n")
            )
            ->addBody('    default => throw new Exception("Unhandled response status: {$status}")')
            ->addBody('};')
            ->addBody('return $responseCls::deserialize($response->json(), $responseCls);');
        $createDtoMethod
            ->addParameter('response')
            ->setType(Response::class);

        if ($endpoint->bodySchema) {
            $classType
                ->addMethod('defaultBody')
                ->setReturnType('array')
                ->addBody(
                    sprintf('return $this->%s->toArray();', NameHelper::safeVariableName($endpoint->bodySchema->name))
                );

            $bodyFQN = $this->bodyFQN($endpoint->bodySchema);
            $namespace->addUse($bodyFQN);
        }

        $namespace
            ->addUse(SaloonHttpMethod::class)
            ->addUse(Request::class)
            ->add($classType);

        return $classFile;
    }

    protected function generateConstructor(Endpoint $endpoint, ClassType $classType): void
    {
        $constructor = $classType->addMethod('__construct');

        // Priority 1. - Path Parameters
        foreach ($endpoint->pathParameters as $pathParam) {
            MethodGeneratorHelper::addParameterToConstructor($constructor, $pathParam);
        }

        // Priority 2. - Body Parameters
        if ($endpoint->bodySchema) {
            $body = $endpoint->bodySchema;

            $properties = [];
            foreach ($body->properties ?? [] as $name => $property) {
                if (in_array($property->name, $this->config->ignoredBodyParams)) {
                    continue;
                }
                $properties[$name] = $property;
            }
            $body->properties = $properties;

            $name = NameHelper::safeVariableName($body->name);

            $property = $constructor
                ->addComment(
                    trim(sprintf(
                        '@param %s $%s %s',
                        $body->nullable ? "?{$body->type}" : $body->type,
                        $name,
                        $body->description
                    ))
                )
                ->addPromotedParameter($name);

            $bodyFQN = $this->bodyFQN($body);
            $property
                ->setType($bodyFQN)
                ->setNullable($body->nullable)
                ->setProtected();

            if ($body->nullable) {
                $property->setDefaultValue(null);
            }
        }

        // Priority 3. - Query Parameters
        if (! empty($endpoint->queryParameters)) {
            $queryParams = collect($endpoint->queryParameters)
                ->reject(fn (Parameter $parameter) => in_array($parameter->name, $this->config->ignoredQueryParams))
                ->values()
                ->toArray();

            foreach ($queryParams as $queryParam) {
                MethodGeneratorHelper::addParameterToConstructor($constructor, $queryParam);
            }

            MethodGeneratorHelper::generateArrayReturnMethod($classType, 'defaultQuery', $queryParams, withArrayFilterWrapper: true);
        }

        if (count($constructor->getParameters()) === 0) {
            $classType->removeMethod('__construct');
        }
    }

    protected function bodyFQN(Schema $body): string
    {
        $dtoNamespaceSuffix = NameHelper::optionalNamespaceSuffix($this->config->dtoNamespaceSuffix);
        $dtoNamespace = "{$this->config->namespace}{$dtoNamespaceSuffix}";

        return "{$dtoNamespace}\\{$body->name}";
    }
}
