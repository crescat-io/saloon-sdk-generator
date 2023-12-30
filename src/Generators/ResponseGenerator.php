<?php

declare(strict_types=1);

namespace Crescat\SaloonSdkGenerator\Generators;

use Crescat\SaloonSdkGenerator\BaseResponse;
use Crescat\SaloonSdkGenerator\Data\Generator\ApiSpecification;
use Crescat\SaloonSdkGenerator\Data\Generator\Schema;
use Crescat\SaloonSdkGenerator\Enums\SimpleType;
use Crescat\SaloonSdkGenerator\Generator;
use Crescat\SaloonSdkGenerator\Helpers\NameHelper;
use Nette\PhpGenerator\Literal;
use Nette\PhpGenerator\PhpFile;

class ResponseGenerator extends Generator
{
    public function generate(ApiSpecification $specification): PhpFile|array
    {
        $classes = [];

        foreach ($specification->responses as $response) {
            $classes[] = $this->generateResponseClass($response);
        }

        return $classes;
    }

    public function generateResponseClass(Schema $schema): PhpFile
    {
        $className = NameHelper::responseClassName($schema->name);
        [$classFile, $namespace, $classType] = $this->makeClass($className, $this->config->responseNamespaceSuffix);

        $namespace->addUse(BaseResponse::class);

        $classType
            ->setFinal()
            ->setExtends(BaseResponse::class);

        $classConstructor = $classType->addMethod('__construct');

        $dtoNamespaceSuffix = NameHelper::optionalNamespaceSuffix($this->config->dtoNamespaceSuffix);
        $dtoNamespace = "{$this->config->namespace}{$dtoNamespaceSuffix}";
        $complexArrayTypes = [];
        foreach ($schema->properties as $parameterName => $property) {
            $name = NameHelper::safeVariableName($parameterName);
            $param = $classConstructor
                ->addComment(
                    trim(sprintf(
                        '@param %s $%s %s',
                        $property->getDocTypeString(),
                        $name,
                        $property->description
                    ))
                )
                ->addPromotedParameter($name);

            $type = $property->type;
            if (! SimpleType::tryFrom($type)) {
                $type = "{$dtoNamespace}\\{$type}";
                $namespace->addUse($type);
            }

            $param
                ->setReadOnly()
                ->setType($type)
                ->setNullable($property->isNullable())
                ->setPublic();

            if ($property->isNullable()) {
                $param->setDefaultValue(null);
            }

            if ($property->type === SimpleType::ARRAY && $property->items) {
                $complexArrayTypes[$name] = $property->items->type;
            }
        }

        if (count($complexArrayTypes) > 0) {
            foreach ($complexArrayTypes as $name => $type) {
                $dtoFQN = "{$dtoNamespace}\\{$type}";
                $namespace->addUse($dtoFQN);

                $literalType = new Literal(sprintf('%s::class', $type));
                $complexArrayTypes[$name] = [$literalType];
            }
            $classType->addProperty('complexArrayTypes', $complexArrayTypes)
                ->setStatic()
                ->setType('array')
                ->setProtected();
        }

        return $classFile;
    }
}
