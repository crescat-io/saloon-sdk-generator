<?php

declare(strict_types=1);

namespace Crescat\SaloonSdkGenerator\Generators;

use cebe\openapi\spec\Type;
use Crescat\SaloonSdkGenerator\BaseDto;
use Crescat\SaloonSdkGenerator\Data\Generator\ApiSpecification;
use Crescat\SaloonSdkGenerator\Data\Generator\Schema;
use Crescat\SaloonSdkGenerator\Enums\SimpleType;
use Crescat\SaloonSdkGenerator\Generator;
use Crescat\SaloonSdkGenerator\Helpers\MethodGeneratorHelper;
use Crescat\SaloonSdkGenerator\Helpers\NameHelper;
use Crescat\SaloonSdkGenerator\Helpers\Utils;
use Nette\PhpGenerator\Literal;
use Nette\PhpGenerator\PhpFile;

class DtoGenerator extends Generator
{
    public function generate(ApiSpecification $specification): PhpFile|array
    {
        $classes = [];

        foreach ($specification->schemas as $schema) {
            if ($schema->type === SimpleType::ARRAY->value || SimpleType::isScalar($schema->type)) {
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

        $dtoNamespace = $this->config->dtoNamespace();
        $responseNamespace = $this->config->responseNamespace();
        $complexArrayTypes = [];

        foreach ($schema->properties as $parameterName => $property) {
            $property->name = NameHelper::safeVariableName($parameterName);
            MethodGeneratorHelper::addParameterToMethod(
                $classConstructor,
                $property,
                namespace: $dtoNamespace,
                promote: true,
                visibility: 'public',
                readonly: true,
            );

            // Only add to the complex array types list if the property is an array of non-built-in types
            if (
                $property->type === Type::ARRAY
                && $property->items
                && ! Utils::isBuiltInType($property->items->type)
            ) {
                $complexArrayTypes[$property->name] = $property->items->type;
            }
        }

        if ($schema->additionalProperties) {
            $classConstructor->setVariadic(true);
            MethodGeneratorHelper::addParameterToMethod(
                $classConstructor,
                $schema->additionalProperties,
                namespace: $dtoNamespace,
                promote: false,
            );

            $classConstructor->addBody('parent::__construct(...$additionalProperties);');

            // Since additional properties are implicitly an array type, the additional properties type should
            // always get added to the complex array types array if it's not a built-in type.
            if (! SimpleType::tryFrom($schema->additionalProperties->type)) {
                $complexArrayTypes['additionalProperties'] = $schema->additionalProperties->type;
            }
        }

        if (count($complexArrayTypes) > 0) {
            foreach ($complexArrayTypes as $name => $type) {
                $safeType = NameHelper::dtoClassName($type);
                $dtoFQN = "{$dtoNamespace}\\{$safeType}";
                $namespace->addUse($dtoFQN);

                $literalType = new Literal(sprintf('%s::class', $safeType));
                $safeName = NameHelper::safeVariableName($name);
                $complexArrayTypes[$safeName] = [$literalType];
            }
            $classType->addProperty('complexArrayTypes', $complexArrayTypes)
                ->setStatic()
                ->setType('array')
                ->setProtected();
        }

        return $classFile;
    }
}
