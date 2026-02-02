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
use Nette\PhpGenerator\Method;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PhpNamespace;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;

class DtoGenerator extends Generator
{
    protected array $generated = [];

    protected ApiSpecification $specification;

    public function generate(ApiSpecification $specification): PhpFile|array
    {
        $this->specification = $specification;

        if ($specification->components) {
            foreach ($specification->components->schemas as $className => $schema) {
                // Allow subclasses to skip certain schemas
                if (! $this->shouldIncludeSchema($className, $schema)) {
                    continue;
                }

                $this->generateDtoClass(NameHelper::safeClassName($className), $schema);
            }
        }

        return $this->generated;
    }

    /**
     * Determine if a schema should be included in generation.
     */
    protected function shouldIncludeSchema(string $className, Schema $schema): bool
    {
        return true;
    }

    /**
     * Extract properties from the schema.
     *
     * @return Schema[]
     */
    protected function extractProperties(Schema $schema): array
    {
        return $schema->properties ?? [];
    }

    /**
     * Get list of property names to skip during DTO generation.
     *
     * @return string[]
     */
    protected function getPropertiesToSkip(): array
    {
        return [];
    }

    protected function generateDtoClass(string $className, Schema $schema): PhpFile
    {
        /** @var Schema[] $properties */
        $properties = $this->extractProperties($schema);

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
            if (in_array($propertyName, $this->getPropertiesToSkip(), true)) {
                continue;
            }

            $type = $this->convertOpenApiTypeToPhp($propertySpec);

            // Check if this is a reference to another schema
            if ($propertySpec instanceof Reference) {
                // For references, we need to use the DTO class name
                // The schema name from the reference is already the base name (e.g., "User")
                // We need to apply the same transformation as we do for the DTO class names
                $schemaName = $type;
                $dtoClassName = NameHelper::dtoClassName($schemaName);
                // Use the FQN for the type
                $type = "{$this->config->namespace}\\{$this->config->dtoNamespaceSuffix}\\{$dtoClassName}";
                // Track referenced DTOs
                $referencedDtos[] = $dtoClassName;
            }

            $sub = NameHelper::dtoClassName($type);

            if ($type === 'object' || $type == 'array') {

                if (! isset($this->generated[$sub]) && ! empty($propertySpec->properties)) {
                    $this->generated[$sub] = $this->generateDtoClass($propertyName, $propertySpec);
                }
            }

            $mappingGenerated = $this->addPropertyToClass(
                $classType,
                $namespace,
                $propertyName,
                $propertySpec,
                $type,
                $classConstructor
            );

            if ($mappingGenerated) {
                $generatedMappings = true;
            }
        }

        $namespace->addUse(Data::class, alias: 'SpatieData');

        if ($generatedMappings) {
            $namespace->addUse(MapName::class);
        }

        $namespace->add($classType);

        $this->afterDtoClassGenerated($classType, $namespace, $schema);

        $this->generated[$dtoName] = $classFile;

        return $classFile;
    }

    /**
     * Add a property to the DTO class.
     *
     * @return bool Whether a name mapping was generated.
     */
    protected function addPropertyToClass(
        ClassType $classType,
        PhpNamespace $namespace,
        string $propertyName,
        Schema|Reference $propertySpec,
        string $type,
        Method $classConstructor,
    ): bool {
        $name = NameHelper::safeVariableName($propertyName);

        $property = $classConstructor->addPromotedParameter($name)
            ->setPublic()
            ->setDefaultValue(null);

        $property->setType($type);

        if ($name !== $propertyName) {
            $property->addAttribute(MapName::class, [$propertyName]);

            return true;
        }

        return false;
    }

    /**
     * Post-process the generated DTO class.
     */
    protected function afterDtoClassGenerated(ClassType $classType, PhpNamespace $namespace, Schema $schema): void
    {
        //
    }

    protected function convertOpenApiTypeToPhp(Schema|Reference $schema): string
    {
        if ($schema instanceof Reference) {
            return Str::afterLast($schema->getReference(), '/');
        }

        if (is_array($schema->type)) {
            return collect($schema->type)->map(fn ($type) => $this->mapType($type))->implode('|');
        }

        if (is_string($schema->type)) {
            return $this->mapType($schema->type, $schema->format);
        }

        return 'mixed';
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
                'int32', 'int64	' => 'int',
                default => 'int|float',
            },
            'array' => 'array',
            'null' => 'null',
            default => 'mixed',
        };
    }
}
