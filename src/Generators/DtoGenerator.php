<?php

declare(strict_types=1);

namespace Crescat\SaloonSdkGenerator\Generators;

use cebe\openapi\spec\Type;
use Crescat\SaloonSdkGenerator\BaseDto;
use Crescat\SaloonSdkGenerator\Data\Generator\ApiSpecification;
use Crescat\SaloonSdkGenerator\Data\Generator\Schema;
use Crescat\SaloonSdkGenerator\Generator;
use Crescat\SaloonSdkGenerator\Helpers\NameHelper;
use Nette\PhpGenerator\Literal;
use Nette\PhpGenerator\PhpFile;

class DtoGenerator extends Generator
{
    public function generate(ApiSpecification $specification): PhpFile|array
    {
        $classes = [];

        foreach ($specification->schemas as $schema) {
            if ($schema->type === Type::ARRAY || Type::isScalar($schema->type)) {
                continue;
            }
            $classes[] = $this->generateDtoClass($schema);
        }

        return $classes;
    }

    public function generateDtoClass(Schema $schema): PhpFile
    {
        $className = NameHelper::dtoClassName($schema->name);
        [$classFile, $namespace, $classType] = $this->makeClass($className, $this->config->dtoNamespaceSuffix);

        $namespace->addUse(BaseDto::class);

        $classType
            ->setFinal()
            ->setExtends(BaseDto::class);

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
            if (! Type::isScalar($type)) {
                $type = "{$namespace->getName()}\\{$type}";
            }

            $nullable = ! in_array($name, $schema->required ?? []) || $property->nullable;
            $param
                ->setReadOnly()
                ->setType($type)
                ->setNullable($nullable)
                ->setPublic();

            if ($nullable) {
                $param->setDefaultValue(null);
            }

            if ($property->type === Type::ARRAY && $property->items) {
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
