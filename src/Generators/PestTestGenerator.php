<?php

namespace Crescat\SaloonSdkGenerator\Generators;

use Crescat\SaloonSdkGenerator\Data\Generator\ApiSpecification;
use Crescat\SaloonSdkGenerator\Data\Generator\Endpoint;
use Crescat\SaloonSdkGenerator\Generator;
use Crescat\SaloonSdkGenerator\Helpers\NameHelper;
use Illuminate\Support\Str;
use Nette\PhpGenerator\PhpFile;

class PestTestGenerator extends Generator
{
    protected ApiSpecification $specification;

    public function generate(ApiSpecification $specification): PhpFile|array
    {
        $this->specification = $specification;

        return $this->generatePestTests($specification);
    }

    /**
     * @return array|PhpFile[]
     */
    protected function generatePestTests(ApiSpecification $specification): array
    {
        $classes = [];

        $classes[] = $this->generateMainPestFile($specification);
        $classes[] = $this->generateTestCaseFile($specification);

        $groupedByCollection = collect($specification->endpoints)->groupBy(function (Endpoint $endpoint) {
            return NameHelper::resourceClassName(
                $endpoint->collection ?: $this->config->fallbackResourceName
            );
        });

        foreach ($groupedByCollection as $collection => $items) {
            $classes[] = $this->generateTest($collection, $items->toArray());
        }

        return $classes;
    }

    protected function generateMainPestFile(ApiSpecification $specification): PhpFile
    {

        $stub = file_get_contents(__DIR__.'/../Stubs/pest.stub');

        $stub = str_replace('{{ namespace }}', $this->config->namespace, $stub);
        $stub = str_replace('{{ name }}', $this->config->connectorName, $stub);

        return PhpFile::fromCode($stub);
    }

    protected function generateTestCaseFile(ApiSpecification $specification): PhpFile
    {

        $stub = file_get_contents(__DIR__.'/../Stubs/pest-testcase.stub');

        $stub = str_replace('{{ namespace }}', $this->config->namespace, $stub);
        $stub = str_replace('{{ name }}', $this->config->connectorName, $stub);

        return PhpFile::fromCode($stub);
    }
    //
    //    protected function generateDtoNames()
    //    {
    //
    //        $dtoNames = [];
    //
    //        if ($this->specification->components) {
    //            foreach ($this->specification->components->schemas as $className => $schema) {
    //                $dtoName = NameHelper::dtoClassName(
    //                    NameHelper::safeClassName($className) ?: $this->config->fallbackResourceName
    //                );
    //
    //                $dtoNames[] = "use {$this->config->namespace}\\{$this->config->dtoNamespaceSuffix}\\{$dtoName};";
    //            }
    //        }
    //
    //        return $dtoNames;
    //    }

    /**
     * @param  array|Endpoint[]  $endpoints
     */
    public function generateTest(string $resourceName, array $endpoints): ?PhpFile
    {

        $fileStub = file_get_contents(__DIR__.'/../Stubs/pest-resource-test.stub');
        $fileStub = str_replace('{{ name }}', $this->config->connectorName, $fileStub);

        $fileStub = str_replace('{{ namespace }}', $this->config->namespace, $fileStub);
        $fileStub = str_replace('{{ name }}', $this->config->connectorName, $fileStub);

        $imports = [];
        foreach ($endpoints as $endpoint) {
            $requestClassName = NameHelper::resourceClassName($endpoint->name);
            $imports[] = "use {$this->config->namespace}\\{$this->config->requestNamespaceSuffix}\\{$resourceName}\\{$requestClassName};";
        }

        $fileStub = str_replace('{{ requestImports }}', implode("\n", $imports), $fileStub);
        $fileStub = str_replace('{{ dtoImports }}', '', $fileStub);
        // TODO: Implement dto imports
        // $fileStub = str_replace('{{ dtoImports }}', implode("\n",$this->generateDtoNames()), $fileStub);

        foreach ($endpoints as $endpoint) {
            $requestClassName = NameHelper::resourceClassName($endpoint->name);
            $methodName = NameHelper::safeVariableName($requestClassName);
            $requestClassNameAlias = $requestClassName == $resourceName ? "{$requestClassName}Request" : null;
            $requestClassFQN = "{$this->config->namespace}\\{$this->config->requestNamespaceSuffix}\\{$resourceName}\\{$requestClassName}";

            $functionStub = file_get_contents(__DIR__.'/../Stubs/pest-resource-test-func.stub');

            // name
            $functionStub = str_replace('{{ name }}', $this->config->connectorName, $functionStub);

            // requestClass
            $functionStub = str_replace('{{ requestClass }}', $requestClassNameAlias ?? $requestClassName, $functionStub);

            // resourceName
            $functionStub = str_replace('{{ resourceName }}', NameHelper::safeVariableName($requestClassName), $functionStub);

            // methodName
            $functionStub = str_replace('{{ methodName }}', $methodName, $functionStub);

            // fixtureName
            $functionStub = str_replace('{{ fixtureName }}', Str::camel($resourceName.'.'.$methodName), $functionStub);

            // testDescription
            $testDescriptionFallback = "calls the {$methodName} in the {$resourceName} resource";
            $testDescription = trim(Str::limit(addslashes($endpoint->description ?: $testDescriptionFallback), 120));
            $functionStub = str_replace('{{ testDescription }}', $testDescription, $functionStub);

            $fileStub .= "\n\n{$functionStub}";

        }

        file_put_contents(__DIR__.'/../../tests/Build/'.$resourceName.'Test.php', $fileStub);

        $file = PhpFile::fromCode($fileStub);
        dump($fileStub);

        return $file;
    }
}
