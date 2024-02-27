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
     * @return array|PhpFile[]
     */
    protected function generatePestTests(): array
    {
        $classes = [];

        $classes[] = $this->generateMainPestFile();
        $classes[] = $this->generateTestCaseFile();

        $groupedByCollection = collect($this->specification->endpoints)->groupBy(function (Endpoint $endpoint) {
            return NameHelper::resourceClassName(
                $endpoint->collection ?: $this->config->fallbackResourceName
            );
        });

        foreach ($groupedByCollection as $collection => $items) {
            $classes[] = $this->generateTest($collection, $items->toArray());

        }

        return $classes;
    }

    protected function generateMainPestFile(): PhpFile
    {
        $stub = file_get_contents(__DIR__.'/../Stubs/pest.stub');
        $stub = str_replace('{{ namespace }}', $this->config->namespace, $stub);
        $stub = str_replace('{{ name }}', $this->config->connectorName, $stub);

        return PhpFile::fromCode($stub);
    }

    protected function generateTestCaseFile(): PhpFile
    {
        $stub = file_get_contents(__DIR__.'/../Stubs/pest-testcase.stub');
        $stub = str_replace('{{ namespace }}', $this->config->namespace, $stub);
        $stub = str_replace('{{ name }}', $this->config->connectorName, $stub);

        return PhpFile::fromCode($stub);
    }

    /**
     * @param  array|Endpoint[]  $endpoints
     */
    public function generateTest(string $resourceName, array $endpoints): PhpFile|TaggedOutputFile|null
    {

        $fileStub = file_get_contents(__DIR__.'/../Stubs/pest-resource-test.stub');

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
            $requestClassName = NameHelper::resourceClassName($endpoint->name);
            $imports[] = "use {$this->config->namespace}\\{$this->config->requestNamespaceSuffix}\\{$resourceName}\\{$requestClassName};";
        }

        $fileStub = str_replace('{{ requestImports }}', implode("\n", $imports), $fileStub);

        // TODO: Implement dto imports
        // $fileStub = str_replace('{{ dtoImports }}', implode("\n",$this->generateDtoNames()), $fileStub);
        $fileStub = str_replace('{{ dtoImports }}', '', $fileStub);

        foreach ($endpoints as $endpoint) {
            $requestClassName = NameHelper::resourceClassName($endpoint->name);
            $requestClassNameAlias = $requestClassName == $resourceName ? "{$requestClassName}Request" : null;

            $functionStub = file_get_contents(__DIR__.'/../Stubs/pest-resource-test-func.stub');

            $functionStub = str_replace('{{ clientName }}', NameHelper::safeVariableName($this->config->connectorName), $functionStub);
            $functionStub = str_replace('{{ requestClass }}', $requestClassNameAlias ?? $requestClassName, $functionStub);
            $functionStub = str_replace('{{ resourceName }}', $resourceNameSafe = NameHelper::safeVariableName($resourceName), $functionStub);
            $functionStub = str_replace('{{ methodName }}', $methodNameSafe = NameHelper::safeVariableName($requestClassName), $functionStub);
            $functionStub = str_replace('{{ fixtureName }}', Str::camel($resourceNameSafe.'.'.$methodNameSafe), $functionStub);
            $description = "calls the {$methodNameSafe} method in the {$resourceName} resource";
            $functionStub = str_replace('{{ testDescription }}', $description, $functionStub);

            $methodArguments = [];

            $withoutIgnoredQueryParams = collect($endpoint->queryParameters)
                ->reject(fn (Parameter $parameter) => in_array($parameter->name, $this->config->ignoredQueryParams))
                ->values()
                ->toArray();

            $combined = [
                ...$endpoint->pathParameters,
                ...$endpoint->bodyParameters,
                ...$withoutIgnoredQueryParams,
            ];

            foreach ($combined as $param) {
                $methodArguments[] = sprintf('%s: %d', Namehelper::safeVariableName($param->name), match ($param->type) {
                    'integer' => 0,
                    'boolean' => 'true',
                    default => "'replace me'",
                });
            }

            $methodArguments = Str::wrap(implode(",\n\t\t", $methodArguments), "\n\t\t", "\n\t");
            $functionStub = str_replace('{{ methodArguments }}', $methodArguments, $functionStub);

            $fileStub .= "\n\n{$functionStub}";
        }

        try {

            return new TaggedOutputFile(
                tag: 'pest',
                file: $fileStub,
                path: "tests/{$resourceName}Test.php",
            );
        } catch (Exception $e) {

            // TODO: Inform about exception
            return null;
        }

    }
}
