<?php

declare(strict_types=1);

namespace Crescat\SaloonSdkGenerator\Generators;

use Crescat\SaloonSdkGenerator\Data\Generator\ApiSpecification;
use Crescat\SaloonSdkGenerator\Data\Generator\Endpoint;
use Crescat\SaloonSdkGenerator\Generator;
use Crescat\SaloonSdkGenerator\Helpers\NameHelper;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpFile;
use Saloon\Http\Connector;

class ConnectorGenerator extends Generator
{
    public function generate(ApiSpecification $specification): PhpFile|array
    {
        return $this->generateConnectorClass($specification);
    }

    protected function generateConnectorClass(ApiSpecification $specification): ?PhpFile
    {
        $classType = new ClassType($this->config->connectorName);
        $classType->setExtends(Connector::class);

        if ($specification->name) {
            $classType->addComment($specification->name);
        }

        if ($specification->description) {
            $classType->addComment($specification->name ? "\n{$specification->description}" : $specification->description);
        }

        $classFile = new PhpFile();

        $classType->addMethod('resolveBaseUrl')
            ->setReturnType('string')
            ->setBody(
                sprintf("return '%s';", $specification->baseUrl)
            );

        $namespace = $classFile
            ->setStrictTypes()
            ->addNamespace("{$this->config->namespace}")
            ->addUse(Connector::class);

        $collections = collect($specification->endpoints)
            ->map(function (Endpoint $endpoint) {
                return NameHelper::connectorClassName($endpoint->collection ?: $this->config->fallbackResourceName);
            })
            ->unique()
            ->sort()
            ->all();

        foreach ($collections as $collection) {
            $resourceClassName = NameHelper::connectorClassName($collection);
            $resourceFQN = "{$this->config->resourceNamespace()}\\{$resourceClassName}";

            $namespace->addUse($resourceFQN);

            // TODO: method names like "authenticate" will cause name collision with the Connector class methods,
            //  add a list of reserved method names and find a way to rename the method to something else, or add a pre/suffix

            $classType
                ->addMethod(NameHelper::safeVariableName($collection))
                ->setReturnType($resourceFQN)
                ->setBody(
                    sprintf('return new %s($this);', $resourceClassName)
                );

        }

        $namespace->add($classType);

        return $classFile;
    }
}
