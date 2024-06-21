<?php

declare(strict_types=1);

namespace Crescat\SaloonSdkGenerator\Generators;

use Crescat\SaloonSdkGenerator\Data\Generator\ApiSpecification;
use Crescat\SaloonSdkGenerator\Data\Generator\Endpoint;
use Crescat\SaloonSdkGenerator\Data\Generator\Parameter;
use Crescat\SaloonSdkGenerator\Data\Generator\Schema;
use Crescat\SaloonSdkGenerator\Enums\SimpleType;
use Crescat\SaloonSdkGenerator\Helpers\MethodGeneratorHelper;
use Crescat\SaloonSdkGenerator\Helpers\NameHelper;
use Crescat\SaloonSdkGenerator\Helpers\Utils;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Helpers;
use Nette\PhpGenerator\Literal;
use Nette\PhpGenerator\Method;
use Nette\PhpGenerator\PhpFile;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method as SaloonHttpMethod;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Traits\Body\HasFormBody;
use Saloon\Traits\Body\HasJsonBody;
use Saloon\Traits\Body\HasMultipartBody;
use Saloon\Traits\Body\HasStreamBody;
use Saloon\Traits\Body\HasStringBody;
use Saloon\Traits\Body\HasXmlBody;

class RequestGenerator extends BaseRequestGenerator
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
            $className, [$this->config->namespaceSuffixes['request'], $resourceName]
        );

        $baseRequestClass = $this->baseClassFqn();
        $classType->setExtends($baseRequestClass)
            ->setComment($endpoint->name)
            ->addComment('')
            ->addComment(Utils::wrapLongLines($endpoint->description ?? ''));

        if ($endpoint->method->isPost() || $endpoint->method->isPatch() || $endpoint->method->isPut()) {
            $contentType = $endpoint->bodySchema->contentType;
            if ($contentType === 'application/x-www-form-urlencoded') {
                $classType->addTrait(HasFormBody::class);
                $namespace->addUse(HasFormBody::class);
            } elseif ($contentType === 'multipart/form-data') {
                $classType->addTrait(HasMultipartBody::class);
                $namespace->addUse(HasMultipartBody::class);
            } elseif ($contentType === 'text/xml') {
                $classType->addTrait(HasXmlBody::class);
                $namespace->addUse(HasXmlBody::class);
            } elseif ($contentType === 'text/plain') {
                $classType->addTrait(HasStringBody::class);
                $namespace->addUse(HasStringBody::class);
            } elseif ($contentType === 'application/octet-stream') {
                $classType->addTrait(HasStreamBody::class);
                $namespace->addUse(HasStreamBody::class);
            } else {
                $classType->addTrait(HasJsonBody::class);
                $namespace->addUse(HasJsonBody::class);
            }

            $classType->addImplement(HasBody::class);
            $namespace->addUse(HasBody::class);
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
                        ? sprintf('{$this->%s}', NameHelper::safeVariableName($segment))
                        : $segment
                    )
                    ->pipe(fn (Collection $segments) => sprintf('return "/%s";', $segments->implode('/')))
            );

        $responseNamespace = $this->config->responseNamespace();

        $codesByResponseType = collect($endpoint->responses)
            // TODO: We assume JSON is the only response content type for each HTTP status code.
            // We should support multiple response types in the future
            ->mapWithKeys(function (array $response, int $httpCode) use ($namespace, $responseNamespace) {
                if (count($response) === 0) {
                    $cls = "{$this->config->baseFilesNamespace()}\\EmptyResponse";
                } else {
                    $className = NameHelper::responseClassName($response[array_key_first($response)]->name);
                    $cls = "{$responseNamespace}\\{$className}";
                }
                $namespace->addUse($cls);
                $alias = array_flip($namespace->getUses())[$cls];

                return [$httpCode => $alias];
            })
            ->reduce(function (Collection $carry, string $className, int $httpCode) {
                $carry->put(
                    $className,
                    [...$carry->get($className, []), $httpCode]
                );

                return $carry;
            }, collect());

        $namespace
            ->addUse($baseRequestClass)
            ->addUse(Exception::class)
            ->addUse(Response::class);

        $aliasMap = $namespace->getUses();
        $returnType = $codesByResponseType->map(fn (array $codes, string $className) => $aliasMap[$className])->implode('|');
        $createDtoMethod = $classType->addMethod('createDtoFromResponse')
            ->setPublic()
            ->setReturnType($returnType)
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
            $returnValText = $this->generateDefaultBody($endpoint->bodySchema);

            $classType
                ->addMethod('defaultBody')
                ->setReturnType('array')
                ->addBody(
                    sprintf("return {$returnValText};", NameHelper::safeVariableName($endpoint->bodySchema->name))
                );

            $safeName = NameHelper::requestClassName($endpoint->bodySchema->name);
            $bodyFQN = "{$this->config->dtoNamespace()}\\{$safeName}";
            $namespace->addUse($bodyFQN);
        }

        $namespace
            ->addUse(SaloonHttpMethod::class)
            ->addUse(Request::class)
            ->add($classType);

        return $classFile;
    }

    protected function generateConstructor(Endpoint $endpoint, ClassType $classType): Method
    {
        $constructor = $classType->addMethod('__construct');

        // Priority 1. - Path Parameters
        foreach ($endpoint->pathParameters as $pathParam) {
            MethodGeneratorHelper::addParameterToMethod($constructor, $pathParam, promote: true);
        }

        // Priority 2. - Body Parameters
        if ($endpoint->bodySchema) {
            $body = $endpoint->bodySchema;

            MethodGeneratorHelper::addParameterToMethod(
                $constructor,
                $body,
                namespace: $this->config->dtoNamespace(),
                promote: true,
                visibility: 'public',
            );
        }

        // Priority 3. - Query Parameters
        if (! empty($endpoint->queryParameters)) {
            $queryParams = collect($endpoint->queryParameters)
                ->reject(fn (Parameter $parameter) => in_array($parameter->name, $this->config->ignoredQueryParams))
                ->values()
                ->toArray();

            foreach ($queryParams as $queryParam) {
                MethodGeneratorHelper::addParameterToMethod($constructor, $queryParam, promote: true);
            }

            MethodGeneratorHelper::generateArrayReturnMethod(
                $classType,
                'defaultQuery',
                $queryParams,
                $this->config->datetimeFormat,
                withArrayFilterWrapper: true
            );
        }

        return $constructor;
    }

    protected function generateDefaultBody(Schema $body): string
    {
        $bodyType = $body->type;

        if (SimpleType::isScalar($bodyType)) {
            $returnValText = '[$this->%s]';
        } elseif ($bodyType === 'DateTime') {
            $returnValText = '[$this->%s->format(\''.$this->config->datetimeFormat.'\')]';
        } elseif (! Utils::isBuiltinType($bodyType)) {
            $returnValText = '$this->%s->toArray()';
        } elseif ($bodyType === 'array') {
            $hasProperties = $body->items->properties;
            if ($hasProperties) {
                $returnValText = 'array_map(fn ($item) => $item->toArray(), $this->%s)';
            } else {
                $returnValText = '$this->%s';
            }
        } else {
            $returnValText = '$this->%s';
        }

        return $returnValText;
    }


    protected function bodyFQN(Schema $body): string
    {
        $dtoNamespace = $this->config->dtoNamespace();
        $safeName = NameHelper::requestClassName($body->name);

        return "{$dtoNamespace}\\{$safeName}";
    }
}
