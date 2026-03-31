<?php

namespace Crescat\SaloonSdkGenerator\Generators;

use cebe\openapi\spec\Reference;
use cebe\openapi\spec\Schema;
use Crescat\SaloonSdkGenerator\Data\Generator\ApiSpecification;
use Crescat\SaloonSdkGenerator\Generator;
use Crescat\SaloonSdkGenerator\Helpers\NameHelper;
use Crescat\SaloonSdkGenerator\Helpers\Utils;
use Illuminate\Support\Str;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpFile;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;

class DtoGenerator extends Generator
{
    protected array $generated = [];

    public function generate(ApiSpecification $specification): PhpFile|array
    {
        if ($specification->components) {
            foreach ($specification->components->schemas as $className => $schema) {
                $this->generateDtoClass(NameHelper::safeClassName($className), $schema);
            }
        }

        return $this->generated;
    }

    protected function generateDtoClass($className, Schema $schema)
    {
        /** @var Schema[] $properties */
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
        $referencedDtos = [];

        foreach ($properties as $propertyName => $propertySpec) {
            $type = $this->convertOpenApiTypeToPhp($propertySpec);

            // Check if this is a reference to another schema
            if ($propertySpec instanceof Reference) {
                $dtoClassName = NameHelper::dtoClassName($type);
                $type = $this->buildDtoFqn($dtoClassName);
                $referencedDtos[] = $dtoClassName;
            }

            $sub = NameHelper::dtoClassName($type);

            if ($type === 'object' || $type == 'array') {

                if (! isset($this->generated[$sub]) && ! empty($propertySpec->properties)) {
                    $this->generated[$sub] = $this->generateDtoClass($propertyName, $propertySpec);
                }
            }

            $name = NameHelper::safeVariableName($propertyName);

            $property = $classConstructor->addPromotedParameter($name)
                ->setPublic()
                ->setDefaultValue(null);

            // Set the property type
            $property->setType($type);

            if ($name != $propertyName) {
                $property->addAttribute(MapName::class, [$propertyName]);
                $generatedMappings = true;
            }
        }

        $namespace->addUse(Data::class, alias: 'SpatieData');

        if ($generatedMappings) {
            $namespace->addUse(MapName::class);
        }

        $namespace->add($classType);

        $this->generated[$dtoName] = $classFile;

        return $classFile;
    }

    protected function convertOpenApiTypeToPhp(Schema|Reference $schema)
    {
        if ($schema instanceof Reference) {
            return Str::afterLast($schema->getReference(), '/');
        }

        // Handle anyOf, oneOf, allOf composite types
        if (isset($schema->anyOf) && is_array($schema->anyOf)) {
            return $this->handleCompositeType($schema->anyOf);
        }

        if (isset($schema->oneOf) && is_array($schema->oneOf)) {
            return $this->handleCompositeType($schema->oneOf);
        }

        if (isset($schema->allOf) && is_array($schema->allOf)) {
            return $this->handleCompositeType($schema->allOf);
        }

        // Handle array union types (e.g., type: ["string", "null"])
        if (is_array($schema->type)) {
            return collect($schema->type)->map(fn ($type) => $this->mapType($type, $schema->format ?? null))->implode('|');
        }

        if (is_string($schema->type)) {
            return $this->mapType($schema->type, $schema->format);
        }

        return 'mixed';
    }

    /**
     * Handle anyOf, oneOf, allOf composite types and return a PHP union type string.
     *
     * @param  array<Reference|Schema>  $types
     */
    protected function handleCompositeType(array $types): string
    {
        $phpTypes = [];

        foreach ($types as $typeSchema) {
            if ($typeSchema instanceof Reference) {
                $schemaName = Str::afterLast($typeSchema->getReference(), '/');
                $dtoClassName = NameHelper::dtoClassName($schemaName);
                $phpTypes[] = $this->buildDtoFqn($dtoClassName);
            } elseif ($typeSchema instanceof Schema) {
                $phpTypes[] = $this->extractTypeFromSchema($typeSchema);
            }
        }

        $uniqueTypes = collect($phpTypes)
            ->flatten()
            ->unique()
            ->filter()
            ->implode('|');

        return $uniqueTypes ?: 'mixed';
    }

    /**
     * Extract PHP type(s) from a Schema, handling nested unions.
     *
     * @return string|array<string>
     */
    protected function extractTypeFromSchema(Schema $schema): string|array
    {
        if ($schema->type === null) {
            return 'mixed';
        }

        if (is_array($schema->type)) {
            return array_map(
                fn ($t) => $this->mapType($t, $schema->format ?? null),
                $schema->type
            );
        }

        return $this->mapType($schema->type, $schema->format ?? null);
    }

    /**
     * Build the fully-qualified namespace for a DTO class.
     */
    protected function buildDtoFqn(string $dtoClassName): string
    {
        return "{$this->config->namespace}\\{$this->config->dtoNamespaceSuffix}\\{$dtoClassName}";
    }

    protected function mapType($type, $format = null): string
    {
        return match ($type) {
            'integer' => 'int',
            'string' => 'string',
            'boolean' => 'bool',
            'object' => 'object', // Recurse
            'number' => match ($format) {
                'float' => 'float',
                'int32', 'int64' => 'int',
                default => 'int|float',
            },
            'array' => 'array',
            'null' => 'null',
            default => 'mixed',
        };
    }
}
