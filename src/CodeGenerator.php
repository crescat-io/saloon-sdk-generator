<?php

namespace Crescat\SaloonSdkGenerator;

use Crescat\SaloonSdkGenerator\Contracts\Parser;
use Crescat\SaloonSdkGenerator\Data\Generator\CodeGenerationResult;
use Crescat\SaloonSdkGenerator\Data\Generator\Endpoint;
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
use Saloon\Contracts\Connector;
use Saloon\Enums\Method as SaloonHttpMethod;
use Saloon\Http\Request;

class CodeGenerator
{
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

    public function run(Parser $parser): CodeGenerationResult
    {
        $endpoints = $parser->parse();

        return new CodeGenerationResult(
            requestClasses: $this->generateRequestClasses($endpoints),
            resourceClasses: $this->generateResourceClasses($endpoints),
            dtoClasses: $this->generateDTOs($endpoints),
            connectorClass: $this->generateConnectorClass($endpoints),
            resourceBaseClass: $this->generateResourceBaseClass(),
        );
    }

    /**
     * @param  array|Endpoint[]  $endpoints
     * @return array|PhpFile[]
     */
    protected function generateRequestClasses(array $endpoints): array
    {
        $classes = [];

        foreach ($endpoints as $endpoint) {
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
            ->addComment(Utils::wrapLongLines($endpoint->name ?? ''));

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

        foreach ($endpoint->allParameters() as $parameter) {
            $this->addPromotedPropertyToMethod($classConstructor, $parameter);
        }

        // TODO: skip if no params
        $this->generateMethodReturningParamsAsArray($classType, 'defaultBody', $endpoint->bodyParameters);

        // TODO: skip if no params
        $this->generateMethodReturningParamsAsArray($classType, 'defaultQuery', $endpoint->queryParameters);

        $classFile = new PhpFile;
        $classFile->addNamespace("{$this->namespace}\\{$this->requestNamespaceSuffix}\\{$resourceName}")
            ->addUse(Method::class)
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
     * @param  array|Endpoint[]  $endpoints
     * @return array|PhpFile[]
     */
    protected function generateResourceClasses(array $endpoints): array
    {
        $classes = [];

        $groupedByCollection = collect($endpoints)->groupBy(function (Endpoint $endpoint) {
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

                $method = $classType
                    ->addMethod($this->safeVariableName($endpoint->name));
            } catch (InvalidStateException $exception) {
                $unduplicated = $this->safeVariableName(
                    $endpoint->name.' '.Str::random(3)
                );
                dump('DUPLICATE: '.$this->safeVariableName($endpoint->name).' -> '.$unduplicated);

                // TODO: handle more gracefully in the future
                $method = $classType->addMethod($unduplicated);
            }

            foreach ($endpoint->allParameters() as $parameter) {
                $this->addPropertyToMethod($method, $parameter);
            }

            $args = [];
            foreach ($endpoint->allParameters() as $parameter) {
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
     * @param  array|Endpoint[]  $endpoints
     * @return array|PhpFile[]
     */
    protected function generateDTOs(array $endpoints): array
    {
        // TODO: Implement generating DTOs for endpoints
        return [];
    }

    /**
     * @param  array|Endpoint[]  $endpoints
     */
    protected function generateConnectorClass(array $endpoints): ?PhpFile
    {
        $classType = new ClassType($this->connectorName);

        $classType
            ->addImplement(Connector::class);

        $collections = collect($endpoints)
            ->map(function (Endpoint $endpoint) {
                return $this->safeClassName($endpoint->collection ?: $this->fallbackResourceName);
            })
            ->unique()
            ->sort()
            ->all();

        foreach ($collections as $collection) {
            $classType->addMethod($this->safeVariableName($collection))
                ->setBody(
                    new Literal(sprintf('return new %s($this)', $this->safeClassName($collection)))
                );

        }

        $classFile = new PhpFile();
        $classFile->addNamespace("{$this->namespace}")
            ->addUse(Connector::class)
            ->add($classType);

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
        return Str::camel($this->normalize($value));
    }

    protected function safeClassName(string $value): string
    {
        return Str::studly($this->normalize($value));
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
