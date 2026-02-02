<?php

namespace Crescat\SaloonSdkGenerator\Generators;

use Crescat\SaloonSdkGenerator\Contracts\PostProcessor;
use Crescat\SaloonSdkGenerator\Data\Generator\ApiSpecification;
use Crescat\SaloonSdkGenerator\Data\Generator\Config;
use Crescat\SaloonSdkGenerator\Data\Generator\Endpoint;
use Crescat\SaloonSdkGenerator\Data\Generator\GeneratedCode;
use Crescat\SaloonSdkGenerator\Data\Generator\Parameter;
use Crescat\SaloonSdkGenerator\Data\TaggedOutputFile;
use Crescat\SaloonSdkGenerator\Helpers\NameHelper;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Nette\PhpGenerator\PhpFile;

class PestTestGenerator implements PostProcessor
{
    protected Config $config;

    protected ApiSpecification $specification;

    protected GeneratedCode $generatedCode;

    public function process(
        Config $config,
        ApiSpecification $specification,
        GeneratedCode $generatedCode,
    ): PhpFile|array|null {
        $this->config = $config;
        $this->specification = $specification;
        $this->generatedCode = $generatedCode;

        return $this->generatePestTests();
    }

    /**
     * @return array|TaggedOutputFile[]
     */
    protected function generatePestTests(): array
    {
        $classes = [];

        if ($this->shouldGeneratePestFile()) {
            $classes[] = $this->generateMainPestFile();
        }

        if ($this->shouldGenerateTestCaseFile()) {
            $classes[] = $this->generateTestCaseFile();
        }

        $groupedByCollection = collect($this->specification->endpoints)
            ->filter(fn (Endpoint $endpoint) => $this->shouldIncludeEndpoint($endpoint))
            ->groupBy(function (Endpoint $endpoint) {
                return NameHelper::resourceClassName(
                    $endpoint->collection ?: $this->config->fallbackResourceName
                );
            });

        foreach ($groupedByCollection as $collection => $items) {
            $classes[] = $this->generateTest($collection, $items->toArray());

        }

        return $classes;
    }

    protected function generateMainPestFile(): TaggedOutputFile
    {
        $stub = file_get_contents(__DIR__.'/../Stubs/pest.stub');
        $stub = str_replace('{{ namespace }}', $this->config->namespace, $stub);
        $stub = str_replace('{{ name }}', $this->config->connectorName, $stub);

        return new TaggedOutputFile(
            tag: 'pest',
            file: $stub,
            path: 'tests/Pest.php',
        );
    }

    protected function generateTestCaseFile(): TaggedOutputFile
    {
        $stub = file_get_contents(__DIR__.'/../Stubs/pest-testcase.stub');
        $stub = str_replace('{{ namespace }}', $this->config->namespace, $stub);
        $stub = str_replace('{{ name }}', $this->config->connectorName, $stub);

        return new TaggedOutputFile(
            tag: 'pest',
            file: $stub,
            path: 'tests/TestCase.php',
        );
    }

    /**
     * @param  array|Endpoint[]  $endpoints
     */
    public function generateTest(string $resourceName, array $endpoints): PhpFile|TaggedOutputFile|null
    {

        $fileStub = file_get_contents($this->getTestStubPath());

        $fileStub = str_replace('{{ prelude }}', '// Generated '.date('Y-m-d H:i:s'), $fileStub);
        $fileStub = str_replace('{{ connectorName }}', $this->config->connectorName, $fileStub);
        $fileStub = str_replace('{{ namespace }}', $this->config->namespace, $fileStub);
        $fileStub = str_replace('{{ name }}', $this->config->connectorName, $fileStub);
        $fileStub = str_replace('{{ clientName }}', NameHelper::safeVariableName($this->config->connectorName), $fileStub);

        $namespace = Arr::first($this->generatedCode->connectorClass->getNamespaces());
        $classType = Arr::first($namespace->getClasses());

        $constructorParameters = $classType->getMethod('__construct')->getParameters();

        $constructorArgs = [];
        foreach ($constructorParameters as $parameter) {

            // TODO: Configurable?
            if ($parameter->isNullable()) {
                continue;
            }

            $defaultValue = match ($parameter->getType()) {
                'string' => "'replace'",
                'bool' => 'true',
                'int' => 0,
                default => 'null',
            };

            $constructorArgs[] = $parameter->getName().': '.$defaultValue;
        }

        $fileStub = str_replace('{{ connectorArgs }}', Str::wrap(implode(",\n\t\t", $constructorArgs), "\n\t\t", "\n\t"), $fileStub);

        $imports = [];
        foreach ($endpoints as $endpoint) {
            $requestClassName = $this->getRequestClassName($endpoint);
            $imports[] = "use {$this->config->namespace}\\{$this->config->requestNamespaceSuffix}\\{$resourceName}\\{$requestClassName};";
        }

        $fileStub = str_replace('{{ requestImports }}', implode("\n", $imports), $fileStub);

        // Generate DTO imports
        $dtoImports = $this->generateDtoImports($endpoints);
        $fileStub = str_replace('{{ dtoImports }}', implode("\n", $dtoImports), $fileStub);

        foreach ($endpoints as $endpoint) {
            $requestClassName = $this->getRequestClassName($endpoint);
            $requestClassNameAlias = $requestClassName == $resourceName ? "{$requestClassName}Request" : null;

            $functionStub = file_get_contents($this->getTestFunctionStubPath($endpoint));

            $functionStub = str_replace('{{ clientName }}', NameHelper::safeVariableName($this->config->connectorName), $functionStub);
            $functionStub = str_replace('{{ requestClass }}', $requestClassNameAlias ?? $requestClassName, $functionStub);
            $functionStub = str_replace('{{ resourceName }}', $resourceNameSafe = NameHelper::safeVariableName($resourceName), $functionStub);
            $functionStub = str_replace('{{ methodName }}', $methodNameSafe = $this->getMethodName($endpoint, $requestClassName), $functionStub);
            $functionStub = str_replace('{{ fixtureName }}', Str::camel($resourceNameSafe.'.'.$methodNameSafe), $functionStub);
            $description = "calls the {$methodNameSafe} method in the {$resourceName} resource";
            $functionStub = str_replace('{{ testDescription }}', $description, $functionStub);

            $methodArguments = [];

            $withoutIgnoredQueryParams = collect($endpoint->queryParameters)
                ->reject(fn (Parameter $parameter) => in_array($parameter->name, $this->config->ignoredQueryParams))
                ->values()
                ->toArray();

            $withoutIgnoredHeaderParams = collect($endpoint->headerParameters)
                ->reject(fn (Parameter $parameter) => in_array($parameter->name, $this->config->ignoredHeaderParams))
                ->values()
                ->toArray();

            $combined = [
                ...$endpoint->pathParameters,
                ...$endpoint->bodyParameters,
                ...$withoutIgnoredQueryParams,
                ...$withoutIgnoredHeaderParams,
            ];

            foreach ($combined as $param) {
                // Hook: Allow customization of parameter names in tests
                $paramName = $this->getTestParameterName($param, $endpoint);

                $methodArguments[] = sprintf('%s: %s', $paramName, match ($param->type) {
                    'string' => "'test string'",
                    'int', 'integer' => '123',
                    'float', 'float|int', 'int|float' => '123.45',
                    'bool', 'boolean' => 'true',
                    'array' => '[]',
                    default => 'null',
                });
            }

            $methodArguments = Str::wrap(implode(",\n\t\t", $methodArguments), "\n\t\t", "\n\t");
            $functionStub = str_replace('{{ methodArguments }}', $methodArguments, $functionStub);

            $functionStub = $this->replaceAdditionalStubVariables($functionStub, $endpoint, $resourceName, $requestClassName);

            $fileStub .= "\n\n{$functionStub}";
        }

        try {

            return new TaggedOutputFile(
                tag: 'pest',
                file: $fileStub,
                path: $this->getTestPath($resourceName),
            );
        } catch (Exception $e) {

            // TODO: Inform about exception
            return null;
        }

    }

    /**
     * Generate DTO import statements for endpoints
     *
     * @param  array|Endpoint[]  $endpoints
     */
    protected function generateDtoImports(array $endpoints): array
    {
        $dtoTypes = [];

        foreach ($endpoints as $endpoint) {
            // Extract DTO types from all endpoint parameters
            foreach ($endpoint->allParameters() as $parameter) {
                if ($this->isDtoType($parameter->type)) {
                    $dtoTypes[$parameter->type] = true; // Use associative array to ensure uniqueness
                }
            }
        }

        // Generate use statements for each unique DTO
        $imports = [];
        foreach (array_keys($dtoTypes) as $dtoType) {
            $imports[] = "use {$dtoType};";
        }

        // Sort imports for consistency
        sort($imports);

        return $imports;
    }

    /**
     * Check if a type is a DTO type (fully qualified class name in our DTO namespace)
     */
    protected function isDtoType(string $type): bool
    {
        // Must contain a backslash (namespace separator)
        if (! str_contains($type, '\\')) {
            return false;
        }

        // Must start with our configured namespace
        if (! str_starts_with($type, $this->config->namespace)) {
            return false;
        }

        // Must be in the DTO namespace
        $dtoNamespacePart = "\\{$this->config->dtoNamespaceSuffix}\\";

        return str_contains($type, $dtoNamespacePart);
    }

    /**
     * Hook: Determine if Pest.php should be generated
     */
    protected function shouldGeneratePestFile(): bool
    {
        return true;
    }

    /**
     * Hook: Determine if TestCase.php should be generated
     */
    protected function shouldGenerateTestCaseFile(): bool
    {
        return true;
    }

    /**
     * Hook: Filter endpoints to include in test generation
     */
    protected function shouldIncludeEndpoint(Endpoint $endpoint): bool
    {
        return true;
    }

    /**
     * Hook: Get path to test file stub template
     */
    protected function getTestStubPath(): string
    {
        return __DIR__.'/../Stubs/pest-resource-test.stub';
    }

    /**
     * Hook: Get path to test function stub template
     */
    protected function getTestFunctionStubPath(Endpoint $endpoint): string
    {
        return __DIR__.'/../Stubs/pest-resource-test-func.stub';
    }

    /**
     * Hook: Get request class name for an endpoint
     */
    protected function getRequestClassName(Endpoint $endpoint): string
    {
        return NameHelper::resourceClassName($endpoint->name);
    }

    /**
     * Hook: Get method name for test function
     */
    protected function getMethodName(Endpoint $endpoint, string $requestClassName): string
    {
        return NameHelper::safeVariableName($requestClassName);
    }

    /**
     * Hook: Get the test file path for a resource
     */
    protected function getTestPath(string $resourceName): string
    {
        return "tests/{$resourceName}Test.php";
    }

    /**
     * Hook: Replace additional stub variables to replace in test function stubs
     */
    protected function replaceAdditionalStubVariables(
        string $functionStub,
        Endpoint $endpoint,
        string $resourceName,
        string $requestClassName
    ): string {
        return $functionStub;
    }

    /**
     * Hook: Get parameter name for test method arguments
     *
     * Override this to customize parameter names in tests.
     * You can check if it's a path parameter with: in_array($parameter, $endpoint->pathParameters, true)
     *
     * @param  Parameter  $parameter  The parameter to get the name for
     * @param  Endpoint  $endpoint  The endpoint being tested
     */
    protected function getTestParameterName(Parameter $parameter, Endpoint $endpoint): string
    {
        return NameHelper::safeVariableName($parameter->name);
    }
}
