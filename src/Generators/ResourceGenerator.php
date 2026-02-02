<?php

namespace Crescat\SaloonSdkGenerator\Generators;

use Crescat\SaloonSdkGenerator\Data\Generator\ApiSpecification;
use Crescat\SaloonSdkGenerator\Data\Generator\Endpoint;
use Crescat\SaloonSdkGenerator\Data\Generator\Parameter;
use Crescat\SaloonSdkGenerator\Generator;
use Crescat\SaloonSdkGenerator\Helpers\NameHelper;
use Nette\InvalidStateException;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Literal;
use Nette\PhpGenerator\Method;
use Nette\PhpGenerator\PhpFile;
use Saloon\Http\BaseResource;
use Saloon\Http\Response;

class ResourceGenerator extends Generator
{
    protected array $duplicateRequests = [];

    public function generate(ApiSpecification $specification): PhpFile|array
    {
        return $this->generateResourceClasses($specification);
    }

    /**
     * @return array|PhpFile[]
     */
    protected function generateResourceClasses(ApiSpecification $specification): array
    {
        $classes = [];

        // Hook: Filter endpoints before grouping
        $filteredEndpoints = collect($specification->endpoints)
            ->filter(fn (Endpoint $endpoint) => $this->shouldIncludeEndpoint($endpoint));

        $groupedByCollection = $filteredEndpoints->groupBy(function (Endpoint $endpoint) {
            return NameHelper::resourceClassName(
                $endpoint->collection ?: $this->config->fallbackResourceName
            );
        });

        foreach ($groupedByCollection as $collection => $items) {
            $classes[] = $this->generateResourceClass($collection, $items->toArray());
        }

        return $classes;
    }

    /**
     * @param  array|Endpoint[]  $endpoints
     */
    public function generateResourceClass(string $resourceName, array $endpoints): ?PhpFile
    {
        $classType = new ClassType($resourceName);

        $classType->setExtends(BaseResource::class);

        $classFile = new PhpFile;
        $namespace = $classFile
            ->addNamespace("{$this->config->namespace}\\{$this->config->resourceNamespaceSuffix}")
            ->addUse(BaseResource::class);

        $duplicateCounter = 1;

        foreach ($endpoints as $endpoint) {

            $pathBasedName = NameHelper::pathBasedName($endpoint);
            // Hook: Allow customization of request class name
            $requestClassName = $this->getRequestClassName($endpoint);
            // Hook: Allow customization of method name
            $methodName = $this->getMethodName($endpoint, $requestClassName);
            $requestClassNameAlias = $requestClassName == $resourceName ? "{$requestClassName}Request" : null;
            $requestClassFQN = "{$this->config->namespace}\\{$this->config->requestNamespaceSuffix}\\{$resourceName}\\{$requestClassName}";

            $namespace
                ->addUse(Response::class)
                ->addUse(
                    name: $requestClassFQN,
                    alias: $requestClassNameAlias,
                );

            try {
                $method = $classType->addMethod($methodName);
            } catch (InvalidStateException $exception) {
                // TODO: handle more gracefully in the future
                $deduplicatedMethodName = NameHelper::safeVariableName(
                    sprintf('%s%s', $methodName, 'Duplicate'.$duplicateCounter)
                );
                $duplicateCounter++;

                $this->recordDuplicatedRequestName($requestClassName, $deduplicatedMethodName);

                $method = $classType
                    ->addMethod($deduplicatedMethodName)
                    ->addComment('@todo Fix duplicated method name');
            }

            $method->setReturnType(Response::class);

            $args = [];

            foreach ($endpoint->pathParameters as $parameter) {
                // Hook: Transform path parameter names
                $transformedParam = $this->transformParameter($parameter, true);
                $this->addPropertyToMethod($method, $transformedParam);
                $args[] = new Literal(sprintf('$%s', NameHelper::safeVariableName($transformedParam->name)));
            }

            foreach ($endpoint->bodyParameters as $parameter) {
                if (in_array($parameter->name, $this->config->ignoredBodyParams)) {
                    continue;
                }

                $this->addPropertyToMethod($method, $parameter);
                $args[] = new Literal(sprintf('$%s', NameHelper::safeVariableName($parameter->name)));
            }

            foreach ($endpoint->queryParameters as $parameter) {
                if (in_array($parameter->name, $this->config->ignoredQueryParams)) {
                    continue;
                }
                $this->addPropertyToMethod($method, $parameter);
                $args[] = new Literal(sprintf('$%s', NameHelper::safeVariableName($parameter->name)));
            }

            foreach ($endpoint->headerParameters as $parameter) {
                if (in_array($parameter->name, $this->config->ignoredHeaderParams)) {
                    continue;
                }
                $this->addPropertyToMethod($method, $parameter);
                $args[] = new Literal(sprintf('$%s', NameHelper::safeVariableName($parameter->name)));
            }

            // Hook: Customize resource method (add custom parameters, modify arguments)
            $this->customizeResourceMethod($method, $namespace, $args, $endpoint);

            $method->setBody(
                new Literal(sprintf('return $this->connector->send(new %s(%s));', $requestClassNameAlias ?? $requestClassName, implode(', ', $args)))
            );

            // Hook: Allow post-processing of generated method
            $this->afterMethodGenerated($method, $endpoint, $requestClassName);

        }

        $namespace->add($classType);

        return $classFile;
    }

    protected function addPropertyToMethod(Method $method, Parameter $parameter): Method
    {
        $name = NameHelper::safeVariableName($parameter->name);

        $param = $method
            ->addComment(
                trim(
                    sprintf(
                        '@param %s $%s %s',
                        $parameter->type,
                        $name,
                        $parameter->description
                    )
                )
            )
            ->addParameter($name)
            ->setType($parameter->type)
            ->setNullable($parameter->nullable);

        if ($parameter->nullable) {
            $param->setDefaultValue(null);
        }

        return $method;
    }

    protected function recordDuplicatedRequestName(string $requestClassName, string $deduplicatedMethodName): void
    {
        $this->duplicateRequests[$requestClassName][] = $deduplicatedMethodName;
    }

    /**
     * Hook: Filter endpoints to include in generation
     */
    protected function shouldIncludeEndpoint(Endpoint $endpoint): bool
    {
        return true;
    }

    /**
     * Hook: Customize request class name
     */
    protected function getRequestClassName(Endpoint $endpoint): string
    {
        $pathBasedName = NameHelper::pathBasedName($endpoint);

        return NameHelper::resourceClassName($endpoint->name ?: $pathBasedName);
    }

    /**
     * Hook: Customize method name in Resource class
     *
     * @param  Endpoint  $endpoint  The endpoint
     * @param  string  $requestClassName  The request class name
     */
    protected function getMethodName(Endpoint $endpoint, string $requestClassName): string
    {
        return NameHelper::safeVariableName($requestClassName);
    }

    /**
     * Hook: Transform parameter before adding to method
     *
     * @param  Parameter  $parameter  The parameter to transform
     * @param  bool  $isPathParam  Whether this is a path parameter
     */
    protected function transformParameter(Parameter $parameter, bool $isPathParam): Parameter
    {
        // Hook: Allow customization of parameter name
        $newName = $this->getResourceParameterName($parameter, $isPathParam);

        if ($newName !== $parameter->name) {
            return new Parameter(
                type: $parameter->type,
                nullable: $parameter->nullable,
                name: $newName,
                description: $parameter->description,
            );
        }

        return $parameter;
    }

    /**
     * Hook: Get parameter name for resource method
     *
     * @param  Parameter  $parameter  The parameter to get the name for
     * @param  bool  $isPathParam  Whether this is a path parameter
     */
    protected function getResourceParameterName(Parameter $parameter, bool $isPathParam): string
    {
        return $parameter->name;
    }

    /**
     * Hook: Customize resource method after standard parameters
     *
     * Called after all standard parameters have been added but before method body generation.
     * Use this to add custom parameters and modify the arguments array.
     *
     * Example implementation:
     * <code>
     * // Add Model data parameter for mutation requests
     * if ($endpoint->method->isPost() || $endpoint->method->isPatch()) {
     *     $namespace->addUse(Model::class);
     *     $dataParam = new Parameter(
     *         type: 'Model|array|null',
     *         nullable: true,
     *         name: 'data',
     *         description: 'Request data',
     *     );
     *     $this->addPropertyToMethod($method, $dataParam);
     *     $args[] = new Literal('$data');
     * }
     * </code>
     *
     * @param  Method  $method  The resource method being generated
     * @param  \Nette\PhpGenerator\PhpNamespace  $namespace  The namespace to add imports to
     * @param  array  $args  The method arguments array (pass by reference to modify)
     * @param  Endpoint  $endpoint  The endpoint being generated
     */
    protected function customizeResourceMethod(Method $method, $namespace, array &$args, Endpoint $endpoint): void
    {
        // Default: no customization
    }

    /**
     * Hook: Post-process generated method
     *
     * @param  Method  $method  The generated method
     * @param  Endpoint  $endpoint  The endpoint
     * @param  string  $requestClassName  The request class name
     */
    protected function afterMethodGenerated(Method $method, Endpoint $endpoint, string $requestClassName): void
    {
        // Default: no modifications
    }
}
