<?php

declare(strict_types=1);

namespace Crescat\SaloonSdkGenerator\Normalizers;

use cebe\openapi\ReferenceContext;
use cebe\openapi\spec\Reference;
use cebe\openapi\spec\Schema;
use cebe\openapi\spec\Type;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class SchemaCache
{
    /**
     * @var Collection<string, Schema|Reference>
     */
    private Collection $normalizedSchemas;

    public function __construct()
    {
        $this->normalizedSchemas = collect();
    }

    public function add(?string $name, Schema|Reference $schema): void
    {
        // We occasionally want schemas to be anonymous, like an inline schema in an allOf.
        // There's no good way to name them, and we can parse them without a name
        if (! $name) {
            return;
        }

        $name = $this->getName($schema, $name);

        $this->normalizedSchemas[$name] = $schema;
    }

    public function has(string $name): bool
    {
        return $this->normalizedSchemas->has($name);
    }

    public function reset(): void
    {
        $this->normalizedSchemas = collect([]);
    }

    /**
     * @throws \cebe\openapi\exceptions\UnresolvableReferenceException
     */
    public function getSchema(Schema|Reference $schema, ?string $name, ReferenceContext $context): Schema|Reference|null
    {
        $name = $this->getName($schema, $name);

        if (! $this->has($name ?? '')) {
            return null;
        }

        $normalized = $this->normalizedSchemas[$name];
        if ($normalized instanceof Reference) {
            $normalized = $normalized->resolve($context);
        }

        // It's possible to have two schemas with the same name, but different types
        if ($normalized->type !== $schema->type) {
            return null;
        }

        if (
            count($normalized->properties) !== count($schema->properties)
            || array_keys($normalized->properties) !== array_keys($schema->properties)
        ) {
            return null;
        }

        if ($normalized->description !== $schema->description) {
            return null;
        }

        return $this->normalizedSchemas[$name];

    }

    private function getName(Schema|Reference $schema, ?string $name): ?string
    {
        if ($schema instanceof Schema
            && $schema->type === Type::ARRAY
            && $schema->items instanceof Reference
        ) {
            $name = 'array-'.Str::afterLast($schema->items->getReference(), '/');
        }

        return $name;
    }
}
