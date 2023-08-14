<?php

namespace Crescat\SaloonSdkGenerator;

use Crescat\SaloonSdkGenerator\Data\Generator\ApiSpecification;
use Crescat\SaloonSdkGenerator\Data\Generator\Endpoint;
use Crescat\SaloonSdkGenerator\Data\Generator\GeneratedCode;
use Crescat\SaloonSdkGenerator\Data\Generator\Parameter;
use DateTime;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Nette\InvalidStateException;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Dumper;
use Nette\PhpGenerator\Literal;
use Nette\PhpGenerator\Method;
use Nette\PhpGenerator\PhpFile;
use Saloon\Enums\Method as SaloonHttpMethod;
use Saloon\Http\Connector;
use Saloon\Http\Request;

class CodeGenerator
{
    protected array $variableNameCache = [];

    protected array $classNameCache = [];

    public function __construct(
        protected ?string $namespace,
        protected ?string $resourceNamespaceSuffix,
        protected ?string $requestNamespaceSuffix,
        protected ?string $dtoNamespaceSuffix,
        protected ?string $connectorName,
        protected ?string $outputFolder,
        protected array $ignoredQueryParams = [],
        protected array $ignoredBodyParams = [],
        protected string $fallbackResourceName = 'Misc',
    ) {
    }

    public function run(ApiSpecification $endpoints): GeneratedCode
    {
        return new GeneratedCode(
            requestClasses: $this->generateRequestClasses($endpoints),
            resourceClasses: $this->generateResourceClasses($endpoints),
            dtoClasses: $this->generateDTOs($endpoints),
            connectorClass: $this->generateConnectorClass($endpoints),
            resourceBaseClass: $this->generateResourceBaseClass(),
        );
    }

    /**
     * @return array|PhpFile[]
     */
    protected function generateRequestClasses(ApiSpecification $endpoints): array
    {
        $classes = [];

        foreach ($endpoints->endpoints as $endpoint) {
            $classes[] = $this->generateRequestClass($endpoint);
        }

        return $classes;
    }

    protected function generateRequestClass(Endpoint $endpoint): PhpFile
    {
        $resourceName = $this->safeClassName($endpoint->collection ?: $this->fallbackResourceName);
        $className = $this->safeClassName($endpoint->name);

        $classType = new ClassType($className);

        $classType->setExtends(Request::class)
            ->setComment($endpoint->name)
            ->addComment('')
            ->addComment(Utils::wrapLongLines($endpoint->description ?? ''));

        $classType->addProperty('method')
            ->setProtected()
            ->setType(SaloonHttpMethod::class)
            ->setValue(
                new Literal(
                    sprintf('Method::%s', strtoupper($endpoint->method))
                )
            );

        $classType->addMethod('resolveEndpoint')
            ->setPublic()
            ->setReturnType('string')
            ->addBody(
                collect($endpoint->pathSegments)
                    ->map(function ($segment) {
                        return Str::startsWith($segment, ':')
                            ? new Literal(sprintf('{$this->%s}', $this->safeVariableName($segment)))
                            : $segment;
                    })
                    ->pipe(function (Collection $segments) {
                        return new Literal(sprintf('return "/%s";', $segments->implode('/')));
                    })

            );

        $classConstructor = $classType->addMethod('__construct');

        //        if ($className == "RetrieveAccountBalancesV2") {
        //            dd($endpoint);
        //        }
        // Priority 1. - Path Parameters
        foreach ($endpoint->pathParameters as $pathParam) {
            $this->addPromotedPropertyToMethod($classConstructor, $pathParam);
        }

        // Priority 2. - Body Parameters
        if (! empty($endpoint->bodyParameters)) {
            $bodyParams = collect($endpoint->bodyParameters)
                ->reject(fn (Parameter $parameter) => in_array($parameter->name, $this->ignoredBodyParams))
                ->values()
                ->toArray();

            foreach ($bodyParams as $bodyParam) {
                $this->addPromotedPropertyToMethod($classConstructor, $bodyParam);
            }

            $this->generateMethodReturningParamsAsArray($classType, 'defaultBody', $bodyParams);
        }

        // Priority 3. - Query Parameters
        if (! empty($endpoint->queryParameters)) {
            $queryParams = collect($endpoint->queryParameters)
                ->reject(fn (Parameter $parameter) => in_array($parameter->name, $this->ignoredQueryParams))
                ->values()
                ->toArray();

            foreach ($queryParams as $queryParam) {
                $this->addPromotedPropertyToMethod($classConstructor, $queryParam);
            }

            $this->generateMethodReturningParamsAsArray($classType, 'defaultQuery', $queryParams);
        }

        $classFile = new PhpFile;
        $classFile->addNamespace("{$this->namespace}\\{$this->requestNamespaceSuffix}\\{$resourceName}")
            ->addUse(SaloonHttpMethod::class)
            ->addUse(DateTime::class)
            ->addUse(Request::class)
            ->add($classType);

        return $classFile;
    }

    protected function generateMethodReturningParamsAsArray(ClassType $classType, string $name, array $parameters): Method
    {
        $array = collect($parameters)
            ->mapWithKeys(function (Parameter $parameter) {
                return [
                    $parameter->name => new Literal(
                        sprintf('$this->%s', $this->safeVariableName($parameter->name))
                    ),
                ];
            })
            ->toArray();

        return $classType
            ->addMethod($name)
            ->setReturnType('array')
            ->addBody(sprintf('return %s;', (new Dumper)->dump($array)));
    }

    protected function addPropertyToMethod(Method $method, Parameter $parameter): Method
    {
        $name = $this->safeVariableName($parameter->name);

        $method
            ->addComment(
                trim(sprintf('@param %s $%s %s', $parameter->type, $name, $parameter->description))
            )
            ->addParameter($name)
            ->setType($parameter->type)
            ->setNullable(false);

        return $method;
    }

    protected function addPromotedPropertyToMethod(Method $method, Parameter $parameter): Method
    {
        $name = $this->safeVariableName($parameter->name);

        $method
            ->addComment(
                trim(sprintf('@param %s $%s %s', $parameter->type, $name, $parameter->description))
            )
            ->addPromotedParameter($name)
            ->setType($parameter->type)
            ->setNullable(false)
            ->setProtected();

        return $method;
    }

    /**
     * @return array|PhpFile[]
     */
    protected function generateResourceClasses(ApiSpecification $endpoints): array
    {
        $classes = [];

        $groupedByCollection = collect($endpoints->endpoints)->groupBy(function (Endpoint $endpoint) {
            return $this->safeClassName(
                $endpoint->collection ?: $this->fallbackResourceName
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

        $classType->setExtends("{$this->namespace}\\Resource");

        $classFile = new PhpFile;
        $namespace = $classFile
            ->addNamespace("{$this->namespace}\\{$this->resourceNamespaceSuffix}")
            ->addUse("{$this->namespace}\\Resource");

        foreach ($endpoints as $endpoint) {
            $requestClassName = $this->safeClassName($endpoint->name);

            $namespace->addUse(
                "{$this->namespace}\\{$this->requestNamespaceSuffix}\\{$resourceName}\\{$requestClassName}"
            );

            try {
                $method = $classType->addMethod($this->safeVariableName($endpoint->name));
            } catch (InvalidStateException $exception) {
                $unduplicated = $this->safeVariableName(
                    $endpoint->name.' '.Str::random(3)
                );
                dump('DUPLICATE: '.$this->safeVariableName($endpoint->name).' -> '.$unduplicated);

                // TODO: handle more gracefully in the future
                $method = $classType->addMethod($unduplicated);
            }

            $args = [];

            foreach ($endpoint->pathParameters as $parameter) {
                $this->addPropertyToMethod($method, $parameter);
                $args[] = new Literal(sprintf('$%s', $this->safeVariableName($parameter->name)));
            }

            foreach ($endpoint->bodyParameters as $parameter) {
                if (in_array($parameter->name, $this->ignoredBodyParams)) {
                    continue;
                }

                $this->addPropertyToMethod($method, $parameter);
                $args[] = new Literal(sprintf('$%s', $this->safeVariableName($parameter->name)));
            }

            foreach ($endpoint->queryParameters as $parameter) {
                if (in_array($parameter->name, $this->ignoredQueryParams)) {
                    continue;
                }
                $this->addPropertyToMethod($method, $parameter);
                $args[] = new Literal(sprintf('$%s', $this->safeVariableName($parameter->name)));
            }

            $method->setBody(
                new Literal(sprintf('return $this->connector->send(new %s(%s));', $requestClassName, implode(', ', $args)))
            );

        }

        $namespace->add($classType);

        return $classFile;
    }

    /**
     * @return array|PhpFile[]
     */
    protected function generateDTOs(ApiSpecification $endpoints): array
    {
        // TODO: Implement generating DTOs for endpoints
        return [];
    }

    protected function generateConnectorClass(ApiSpecification $endpoints): ?PhpFile
    {
        $classType = new ClassType($this->connectorName);
        $classType->setExtends(Connector::class);

        if ($endpoints->name) {
            $classType->addComment($endpoints->name);
        }

        if ($endpoints->description) {
            $classType->addComment($endpoints->name ? "\n{$endpoints->description}" : $endpoints->description);
        }

        $classFile = new PhpFile();

        $classType->addMethod('resolveBaseUrl')
            ->setReturnType('string')
            ->setBody(
                new Literal(sprintf(sprintf("return '%s';", $endpoints->baseUrl ?? 'TODO')))
            );

        $namespace = $classFile
            ->addNamespace("{$this->namespace}")
            ->addUse(Connector::class);

        $collections = collect($endpoints->endpoints)
            ->map(function (Endpoint $endpoint) {
                return $this->safeClassName($endpoint->collection ?: $this->fallbackResourceName);
            })
            ->unique()
            ->sort()
            ->all();

        foreach ($collections as $collection) {
            $resourceClassName = $this->safeClassName($collection);
            $resourceFQN = "{$this->namespace}\\{$this->resourceNamespaceSuffix}\\{$resourceClassName}";

            $namespace->addUse($resourceFQN);

            // TODO: method names like "authenticate" will cause name collision with the Connector class methods,
            //  add a blacklist of reserved method names and find a way to rename the method to something else, or add a pre/suffix

            $classType
                ->addMethod($this->safeVariableName($collection))
                ->setReturnType($resourceFQN)
                ->setBody(
                    new Literal(sprintf('return new %s($this);', $resourceClassName))
                );

        }

        $namespace->add($classType);

        return $classFile;
    }

    protected function normalize(string $value): string
    {
        return Str::of($value)
            ->replaceMatches('/([a-z])([A-Z])/', '$1 $2') // YearEndNote -> Year End Note
            ->replace(' a ', ' ')
            ->replace(' an ', ' ')
            ->replace("'s ", ' ')
            ->replace(':', ' ')
            ->replace('.', ' ')
            ->replace(',', ' ')
            ->replace('(', ' ')
            ->replace(')', ' ')
            ->replace('/', ' ')
            ->replace('-', ' ')
            ->replace('_', ' ')
            ->slug(' ')
            ->squish()
            ->trim();
    }

    protected function safeVariableName(string $value): string
    {
        if (isset($this->variableNameCache[$value])) {
            return $this->variableNameCache[$value];
        }

        $result = Str::camel($this->normalize($value));
        $this->variableNameCache[$value] = $result;

        return $result;
    }

    protected function safeClassName(string $value): string
    {
        if (isset($this->classNameCache[$value])) {
            return $this->classNameCache[$value];
        }

        $result = Str::studly($this->normalize($value));
        $this->classNameCache[$value] = $result;

        return $result;
    }

    protected function generateResourceBaseClass(): PhpFile
    {
        $classType = new ClassType('Resource');
        $classType
            ->addMethod('__construct')
            ->addPromotedParameter('connector')
            ->setType(Connector::class)
            ->setProtected();

        $classFile = new PhpFile();
        $classFile->addNamespace("{$this->namespace}")
            ->addUse(Connector::class)
            ->add($classType);

        return $classFile;
    }
}
