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
use Nette\PhpGenerator\PhpFile;
use Saloon\Enums\Method;
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
        );
    }

    /**
     * @param  array|Endpoint[]  $endpoints
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

        $classNamespace = "{$this->namespace}\\{$this->requestNamespaceSuffix}\\{$resourceName}";

        $classType = new ClassType($className);

        $classType->setExtends(Request::class)
            ->setComment($endpoint->name)
            ->addComment('')
            ->addComment(Utils::wrapLongLines($endpoint->name ?? ''));

        $classType->addProperty('method')
            ->setProtected()
            ->setType(Method::class)
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

        /** @var Parameter[] $parameters */
        $parameters = [
            ...$endpoint->pathParameters,
            ...$endpoint->bodyParameters,
            ...$endpoint->queryParameters,
        ];

        $classConstructor = $classType->addMethod('__construct');

        foreach ($parameters as $parameter) {

            $name = $this->safeVariableName($parameter->name);

            $classConstructor
                ->addComment(
                    trim(sprintf('@param %s $%s %s', $parameter->type, $name, $parameter->description))
                )
                ->addPromotedParameter($name)
                ->setType($parameter->type)
                ->setNullable(false)
                ->setProtected();
        }
 
        //        $this->buildQueryParamMethod($item, $classType);
        //        $this->buildBodyDataMethod($item, $classType);

        $classFile = new PhpFile;
        $classFile->addNamespace($classNamespace)
            ->addUse(Method::class)
            ->addUse(DateTime::class)
            ->addUse(Request::class)
            ->add($classType);

        dump((string) $classFile);

        return $classFile;

    }

    /**
     * @param  array|Endpoint[]  $endpoints
     * @return array|PhpFile[]
     */
    protected function generateResourceClasses(array $endpoints): array
    {
        // TODO: Implement generating resource classes for item groups
        return [];
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
}
