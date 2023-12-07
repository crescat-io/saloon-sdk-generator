<?php

namespace Crescat\SaloonSdkGenerator\Generators;

use Crescat\SaloonSdkGenerator\Data\Generator\ApiSpecification;
use Crescat\SaloonSdkGenerator\Data\Generator\Endpoint;
use Crescat\SaloonSdkGenerator\Data\Generator\Parameter;
use Crescat\SaloonSdkGenerator\Data\Generator\SecurityScheme;
use Crescat\SaloonSdkGenerator\Data\Generator\ServerParameter;
use Crescat\SaloonSdkGenerator\Generator;
use Crescat\SaloonSdkGenerator\Helpers\MethodGeneratorHelper;
use Crescat\SaloonSdkGenerator\Helpers\NameHelper;
use Crescat\SaloonSdkGenerator\Helpers\TemplateHelper;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Literal;
use Nette\PhpGenerator\Method;
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

        $this->addConstructor($classType, $specification);
        $this->addResolveBaseUrlMethod($classType, $specification);

        $namespace = $classFile
            ->addNamespace("{$this->config->namespace}")
            ->addUse(Connector::class);

        $this->addMethodsForResources($namespace, $classType, $specification);

        $namespace->add($classType);

        return $classFile;
    }

    protected function addConstructor(
        ClassType $classType,
        ApiSpecification $specification
    ): ClassType {
        $classConstructor = $classType->addMethod('__construct');

        $this->addBaseUrlParametersToContructor($classConstructor, $specification);
        $this->addAuthToConstructor($classConstructor, $specification);

        return $classType;
    }

    protected function addBaseUrlParametersToContructor(
        Method $classConstructor,
        ApiSpecification $specification
    ): Method {
        array_map(function (ServerParameter $param) use ($classConstructor) {
            MethodGeneratorHelper::addParameterAsPromotedProperty(
                $classConstructor,
                new Parameter('string', false, $param->name, $param->description)
            );
        }, $specification->baseUrl->parameters);

        return $classConstructor;
    }

    protected function addResolveBaseUrlMethod(ClassType $classType, ApiSpecification $specification): ClassType
    {
        $params = [];
        foreach ($specification->baseUrl->parameters as $parameter) {
            $params[$parameter->name] = sprintf('{$this->%s}', NameHelper::safeVariableName($parameter->name));
        }
        $baseUrlWithParams = TemplateHelper::render($specification->baseUrl->url ?? 'TODO', $params);

        $classType->addMethod('resolveBaseUrl')
            ->setReturnType('string')
            ->setBody(new Literal(sprintf('return "%s";', $baseUrlWithParams)));

        return $classType;
    }

    protected function addAuthToConstructor(
        Method $classConstructor,
        ApiSpecification $specification,
        string $preferredSecurity = SecurityScheme::TYPE_API_KEY
    ): Method {
        // API Key support
        foreach ($specification->securityRequirements as $securityRequirement) {
            foreach ($specification->components->securitySchemes as $securityScheme) {
                if ($securityRequirement->name === $securityScheme->name) {
                    if ($securityScheme->type === SecurityScheme::TYPE_API_KEY
                        && $preferredSecurity === SecurityScheme::TYPE_API_KEY) {

                        $parameter = $this->getApiKeyParameter($securityScheme);
                        MethodGeneratorHelper::addParameterAsPromotedProperty($classConstructor, $parameter);

                        $classConstructor->addBody(sprintf('$this->withTokenAuth($%s);', $parameter->name));
                    }
                }
            }
        }

        return $classConstructor;
    }

    protected function getApiKeyParameter(SecurityScheme $securityScheme): Parameter
    {
        // Remove X- header prefix
        $name = preg_replace('/^X-/', '', $securityScheme->name);

        return new Parameter(
            'string',
            false,
            NameHelper::safeVariableName($name),
            $securityScheme->description
        );
    }

    protected function addMethodsForResources(
        $namespace,
        ClassType $classType,
        ApiSpecification $specification
    ): ClassType {
        $collections = collect($specification->endpoints)
            ->map(function (Endpoint $endpoint) {
                return NameHelper::connectorClassName($endpoint->collection ?: $this->config->fallbackResourceName);
            })
            ->unique()
            ->sort()
            ->all();

        foreach ($collections as $collection) {
            $resourceClassName = NameHelper::connectorClassName($collection);
            $resourceFQN = "{$this->config->namespace}\\{$this->config->resourceNamespaceSuffix}\\{$resourceClassName}";

            $namespace->addUse($resourceFQN);

            // TODO: method names like "authenticate" will cause name collision with the Connector class methods,
            //  add a list of reserved method names and find a way to rename the method to something else, or add a pre/suffix

            $classType
                ->addMethod(NameHelper::safeVariableName($collection))
                ->setReturnType($resourceFQN)
                ->setBody(
                    new Literal(sprintf('return new %s($this);', $resourceClassName))
                );

        }

        return $classType;
    }
}
