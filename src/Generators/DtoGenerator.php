<?php

namespace Crescat\SaloonSdkGenerator\Generators;

use Crescat\SaloonSdkGenerator\Data\Generator\ApiSpecification;
use Crescat\SaloonSdkGenerator\Data\Generator\Parameter;
use Crescat\SaloonSdkGenerator\Generator;
use Crescat\SaloonSdkGenerator\Helpers\NameHelper;
use Crescat\SaloonSdkGenerator\Helpers\Utils;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpFile;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;

class DtoGenerator extends Generator
{
    public function generate(ApiSpecification $specification): PhpFile|array
    {
        $items = [];

        if ($specification->components) {
            $this->parseSchemas($specification->components->schemas);
            foreach ($specification->components->schemas as $className => $schema) {
                $items[] = $this->generateDtoClass(NameHelper::safeClassName($className), $schema);
            }
        }

        return $items;
    }

    protected function parseSchemas()
    {

    }

    protected function generateDtoClass($className, \cebe\openapi\spec\Schema $schema)
    {
        $properties = $schema->properties ?? [];

        $dtoName = NameHelper::dtoClassName($className ?: $this->config->fallbackResourceName);

        $classType = new ClassType($dtoName);
        $classFile = new PhpFile;
        $namespace = $classFile
            ->addNamespace("{$this->config->namespace}\\{$this->config->dtoNamespaceSuffix}");

        $classType->setExtends(Data::class)
            ->setComment($schema->title ?? '')
            ->addComment('')
            ->addComment(Utils::wrapLongLines($schema->description ?? ''));

        $classConstructor = $classType->addMethod('__construct');

        $generatedMappings = false;

        foreach ($properties as $propertyName => $propertySpec) {
            $type = $this->convertOpenApiTypeToPhp($propertySpec->type);

            $param = new Parameter(
                type: $type,
                nullable: true,
                name: $propertyName,
            );

            $name = NameHelper::safeVariableName($propertyName);

            $property = $classConstructor->addPromotedParameter($name)
                ->setType($type)
                ->setNullable(true)
                ->setPublic()
                ->setDefaultValue(null);

            // TODO: Make this configurable, its not really necessary if the naming is "sane",
            //  or if the original name is different from the variable name and cant be inferred by snake/camelcasing it.
            if ($name != $propertyName) {
                $property->addAttribute(MapName::class, [$propertyName]);
                $generatedMappings = true;
            }

        }

        $namespace->addUse(Data::class)->add($classType);

        if ($generatedMappings) {
            $namespace->addUse(MapName::class);
        }

        return $classFile;
    }

    protected function convertOpenApiTypeToPhp($openApiType)
    {
        if (is_string($openApiType)) {
            return match ($openApiType) {
                'integer' => 'int',
                'string' => 'string',
                'boolean' => 'bool',
                //                'object' => 'mixed', // TODO: Recurse
                default => 'mixed',
            };
        }

        if (is_array($openApiType)) {
            // ex: "null" and "string" => ?string
            if (count($openApiType) == 2 && in_array('null', $openApiType)) {
                $type = collect($openApiType)->first(fn ($type) => $type != 'null');

                return "?$type";
            }

            return implode('|', array_map(fn ($type) => $this->convertOpenApiTypeToPhp($type), $openApiType));
        }

        return 'mixed';
    }
}
