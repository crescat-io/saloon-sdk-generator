<?php

namespace Crescat\SaloonSdkGenerator\Generators;

use Crescat\SaloonSdkGenerator\Data\Generator\ApiSpecification;
use Crescat\SaloonSdkGenerator\Data\Generator\Endpoint;
use Crescat\SaloonSdkGenerator\Data\Generator\Parameter;
use Crescat\SaloonSdkGenerator\Generator;
use Crescat\SaloonSdkGenerator\Helpers\MethodGeneratorHelper;
use Crescat\SaloonSdkGenerator\Helpers\NameHelper;
use Crescat\SaloonSdkGenerator\Helpers\Utils;
use DateTime;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Literal;
use Nette\PhpGenerator\PhpFile;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method as SaloonHttpMethod;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

class RequestGenerator extends Generator
{
    public function generate(ApiSpecification $specification): PhpFile|array
    {
        $classes = [];

        foreach ($specification->endpoints as $endpoint) {
            // Hook: Allow filtering endpoints
            if (! $this->shouldIncludeEndpoint($endpoint)) {
                continue;
            }

            $classes[] = $this->generateRequestClass($endpoint);
        }

        return $classes;
    }

    protected function generateRequestClass(Endpoint $endpoint): PhpFile
    {
        $resourceName = NameHelper::resourceClassName($endpoint->collection ?: $this->config->fallbackResourceName);

        // Hook: Allow customization of request class name
        $className = $this->getRequestClassName($endpoint);

        $classType = new ClassType($className);

        $classFile = new PhpFile;
        $namespace = $classFile
            ->addNamespace("{$this->config->namespace}\\{$this->config->requestNamespaceSuffix}\\{$resourceName}");

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

        // Hook: Customize request class (add interfaces, traits, properties)
        $this->customizeRequestClass($classType, $namespace, $endpoint);

        $classType->addProperty('method')
            ->setProtected()
            ->setType(SaloonHttpMethod::class)
            ->setValue(
                new Literal(
                    sprintf('Method::%s', $endpoint->method->value)
                )
            );

        $classType->addMethod('resolveEndpoint')
            ->setPublic()
            ->setReturnType('string')
            ->addBody(
                collect($endpoint->pathSegments)
                    ->map(function ($segment) {
                        return Str::startsWith($segment, ':')
                            ? new Literal(sprintf('{$this->%s}', $this->getConstructorParameterName(NameHelper::safeVariableName($segment), true)))
                            : $segment;
                    })
                    ->pipe(function (Collection $segments) {
                        return new Literal(sprintf('return "/%s";', $segments->implode('/')));
                    })

            );

        $classConstructor = $classType->addMethod('__construct');

        // Priority 1. - Path Parameters
        foreach ($endpoint->pathParameters as $pathParam) {
            // Hook: Allow customization of path parameter names
            $customizedParam = clone $pathParam;
            $customizedParam->name = $this->getConstructorParameterName($pathParam->name, true);
            MethodGeneratorHelper::addParameterAsPromotedProperty($classConstructor, $customizedParam);
        }

        // Priority 2. - Body Parameters
        if (! empty($endpoint->bodyParameters)) {
            $bodyParams = collect($endpoint->bodyParameters)
                ->reject(fn (Parameter $parameter) => in_array($parameter->name, $this->config->ignoredBodyParams))
                ->values()
                ->toArray();

            foreach ($bodyParams as $bodyParam) {
                MethodGeneratorHelper::addParameterAsPromotedProperty($classConstructor, $bodyParam);
            }

            MethodGeneratorHelper::generateArrayReturnMethod($classType, 'defaultBody', $bodyParams, withArrayFilterWrapper: true);
        } else {
            // Hook: Customize constructor (add custom parameters, defaultBody method)
            $this->customizeConstructor($classConstructor, $classType, $namespace, $endpoint);
        }

        // Priority 3. - Query Parameters
        if (! empty($endpoint->queryParameters)) {
            $queryParams = collect($endpoint->queryParameters)
                ->reject(fn (Parameter $parameter) => in_array($parameter->name, $this->config->ignoredQueryParams))
                ->reject(fn (Parameter $parameter) => ! $this->shouldIncludeQueryParameter($parameter->name))
                ->values()
                ->toArray();

            foreach ($queryParams as $queryParam) {
                MethodGeneratorHelper::addParameterAsPromotedProperty($classConstructor, $queryParam);
            }

            // Hook: Generate defaultQuery method
            $this->generateDefaultQueryMethod($classType, $namespace, $queryParams, $endpoint);
        }

        // Priority 4. - Header Parameters
        if (! empty($endpoint->headerParameters)) {
            $headerParams = collect($endpoint->headerParameters)
                ->reject(fn (Parameter $parameter) => in_array($parameter->name, $this->config->ignoredHeaderParams))
                ->values()
                ->toArray();

            foreach ($headerParams as $headerParam) {
                MethodGeneratorHelper::addParameterAsPromotedProperty($classConstructor, $headerParam);
            }

            MethodGeneratorHelper::generateArrayReturnMethod($classType, 'defaultHeaders', $headerParams, withArrayFilterWrapper: true);
        }

        $namespace
            ->addUse(SaloonHttpMethod::class)
            ->addUse(DateTime::class)
            ->addUse(Request::class)
            ->add($classType);

        // Hook: Allow final modifications to generated class
        $this->afterRequestClassGenerated($classFile, $endpoint);

        return $classFile;
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

        return NameHelper::requestClassName($endpoint->name ?: $pathBasedName);
    }

    /**
     * Hook: Customize constructor parameter names
     *
     * @param  string  $originalName  The original parameter name
     * @param  bool  $isPathParam  Whether this is a path parameter
     */
    protected function getConstructorParameterName(string $originalName, bool $isPathParam = false): string
    {
        return $originalName;
    }

    /**
     * Hook: Customize request class after basic setup
     *
     * Called after class creation but before properties/methods are added.
     * Use this to add custom interfaces, traits, or class-level modifications.
     *
     * Example implementation:
     * <code>
     * // Add Paginatable interface to collection requests
     * if ($endpoint->method->isGet() && empty($endpoint->pathParameters)) {
     *     $namespace->addUse(Paginatable::class);
     *     $classType->addImplement(Paginatable::class);
     *
     *     $namespace->addUse(HasFilters::class);
     *     $classType->addTrait(HasFilters::class);
     * }
     * </code>
     *
     * @param  ClassType  $classType  The class being generated
     * @param  \Nette\PhpGenerator\PhpNamespace  $namespace  The namespace to add imports to
     * @param  Endpoint  $endpoint  The endpoint being generated
     */
    protected function customizeRequestClass(ClassType $classType, $namespace, Endpoint $endpoint): void
    {
        // Default: no customization
    }

    /**
     * Hook: Customize constructor when no body parameters exist
     *
     * Called after standard parameters but only when endpoint has NO body parameters.
     * Use this to add custom constructor parameters and defaultBody method.
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
     *     MethodGeneratorHelper::addParameterAsPromotedProperty($classConstructor, $dataParam);
     *
     *     $classType->addMethod('defaultBody')
     *         ->setProtected()
     *         ->setReturnType('array')
     *         ->addBody('return $this->data ? $this->data->toJsonApi() : [];');
     * }
     * </code>
     *
     * @param  \Nette\PhpGenerator\Method  $classConstructor  The constructor method
     * @param  ClassType  $classType  The class being generated
     * @param  \Nette\PhpGenerator\PhpNamespace  $namespace  The namespace to add imports to
     * @param  Endpoint  $endpoint  The endpoint being generated
     */
    protected function customizeConstructor($classConstructor, ClassType $classType, $namespace, Endpoint $endpoint): void
    {
        // Default: no customization
    }

    /**
     * Hook: Filter query parameters to include
     */
    protected function shouldIncludeQueryParameter(string $paramName): bool
    {
        return true;
    }

    /**
     * Hook: Generate defaultQuery method
     *
     * Called after query parameters have been added to constructor.
     * Override this to customize how the defaultQuery method is generated.
     *
     * @param  ClassType  $classType  The class being generated
     * @param  \Nette\PhpGenerator\PhpNamespace  $namespace  The namespace to add imports to
     * @param  array  $queryParams  Remaining query parameters after filtering
     * @param  Endpoint  $endpoint  The endpoint being generated
     */
    protected function generateDefaultQueryMethod(ClassType $classType, $namespace, array $queryParams, Endpoint $endpoint): void
    {
        MethodGeneratorHelper::generateArrayReturnMethod($classType, 'defaultQuery', $queryParams, withArrayFilterWrapper: true);
    }

    /**
     * Hook: Perform final modifications to the generated class
     */
    protected function afterRequestClassGenerated(PhpFile $phpFile, Endpoint $endpoint): void
    {
        // Default: no modifications
    }
}
