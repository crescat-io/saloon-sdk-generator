<?php

namespace Crescat\SaloonSdkGenerator;

use Crescat\SaloonSdkGenerator\Contracts\Parser;
use Crescat\SaloonSdkGenerator\Data\Generator\CodeGenerationResult;
use Crescat\SaloonSdkGenerator\Data\Generator\Endpoint;
use Crescat\SaloonSdkGenerator\Data\Generator\Parameter;
use DateTime;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Nette\PhpGenerator\ClassType;
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
        protected array   $ignoredQueryParams = [],
        protected array   $ignoredBodyParams = [],
        protected string  $fallbackResourceName = 'Misc',
    )
    {
    }

    public function run(Parser $parser): CodeGenerationResult
    {
        $endpoints = $parser->parse();

        $resourceBaseClass = $this->generateResourceBaseClass();

        return new CodeGenerationResult(
            requestClasses: $this->generateRequestClasses($endpoints),
            resourceClasses: $this->generateResourceClasses($endpoints),
            dtoClasses: $this->generateDTOs($endpoints),
            connectorClass: $this->generateConnectorClass($endpoints),
            resourceBaseClass: $resourceBaseClass,
        );
    }

    /**
     * @param array|Endpoint[] $endpoints
     * @return array|PhpFile[]
     */
    protected function generateRequestClasses(array $endpoints)
    {
        $classes = [];

        foreach ($endpoints as $endpoint) {
            $classes[] = $this->generateRequestClass($endpoint);
        }

        return $classes;
    }

    protected function generateRequestClass(Endpoint $endpoint): PhpFile
    {
        $resourceName = $endpoint->collection ? Str::studly($endpoint->collection) : $this->fallbackResourceName;
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
            $this->addPropertyToMethod($classConstructor, $parameter);
        }

        //        $this->buildQueryParamMethod($item, $classType);
        //        $this->buildBodyDataMethod($item, $classType);

        $classFile = new PhpFile;
        $classFile->addNamespace("{$this->namespace}\\{$this->requestNamespaceSuffix}\\{$resourceName}")
            ->addUse(Method::class)
            ->addUse(DateTime::class)
            ->addUse(Request::class)
            ->add($classType);

        //        dump((string) $classFile);

        return $classFile;

    }

    protected function addPropertyToMethod(Method $method, Parameter $parameter): Method
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
     * @param array|Endpoint[] $endpoints
     * @return array|PhpFile[]
     */
    protected function generateResourceClasses(array $endpoints): array
    {
        $classes = [];

        $groupedByCollection = collect($endpoints)->groupBy(function (Endpoint $endpoint) {
            return $this->safeClassName($endpoint->collection ?: $this->fallbackResourceName);
        });

        foreach ($groupedByCollection as $collection => $items) {

            dump($collection);
            $this->generateResourceClass($collection, $items->toArray());
        }

        return $classes;
    }

    /**
     * @param array|Endpoint[] $endpoints
     */
    public function generateResourceClass(string $resourceName, array $endpoints): PhpFile
    {

        $classType = new ClassType($resourceName);

        $classType->setExtends(Request::class); // TODO: Change to resource
        //            ->setComment($endpoint->name)
        //            ->addComment('')
        //            ->addComment(Utils::wrapLongLines($endpoint->name ?? ''))

        $classFile = new PhpFile;
        $namespace = $classFile->addNamespace("{$this->namespace}\\{$this->resourceNamespaceSuffix}");


        foreach ($endpoints as $endpoint) {
            $requestClassName = $this->safeClassName($endpoint->name);

            $namespace->addUse(
                "{$this->namespace}\\{$this->requestNamespaceSuffix}\\{$resourceName}\\{$requestClassName}"
            );

            $method = $classType
                ->addMethod($this->safeVariableName($endpoint->name));

            foreach ($endpoint->allParameters() as $parameter) {
                $this->addPropertyToMethod($method, $parameter);
            }


            $requestClassName = $this->safeVariableName($endpoint->name);


            // TODO: forward params into class constructor:
            $method->setBody(
                new Literal("return \$this->connector->send(new {$requestClassName}())")
            );

        }

        $namespace->add($classType);

        dump((string)$classFile);

        return $classFile;
    }

    /**
     * @param array|Endpoint[] $endpoints
     * @return array|PhpFile[]
     */
    protected function generateDTOs(array $endpoints): array
    {
        // TODO: Implement generating DTOs for endpoints
        return [];
    }

    /**
     * @param array|Endpoint[] $endpoints
     */
    protected function generateConnectorClass(array $endpoints): ?PhpFile
    {
        // TODO: Implement generating connector class
        return null;
    }

    protected function normalize(string $value): string
    {
        return Str::of($value)
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
