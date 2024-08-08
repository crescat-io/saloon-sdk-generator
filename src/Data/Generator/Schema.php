<?php

declare(strict_types=1);

namespace Crescat\SaloonSdkGenerator\Data\Generator;

use cebe\openapi\spec\Schema as OpenApiSchema;
use Crescat\SaloonSdkGenerator\Enums\SimpleType;
use InvalidArgumentException;

class Schema extends Parameter
{
    public string $name;

    /**
     * @param  Schema[]  $properties
     */
    public function __construct(
        public string $type,
        public ?string $description,
        public bool $nullable = false,
        public ?string $contentType = null,
        public bool $isResponse = false,
        // Having this default to false conflicts with the OpenAPI spec (where the default for
        // additionalProperties is true), but in reality most of the time, most schemas don't
        // have additional properties
        public Schema|bool $additionalProperties = false,
        ?string $name = null,
        public ?string $rawName = null,

        public ?Schema $parent = null,

        public ?Schema $items = null,
        public ?array $properties = [],
        public ?array $required = null,
        public ?string $bodyContentType = null,
    ) {
        if (is_null($name)) {
            if ($this->parent?->type === SimpleType::ARRAY->value) {
                $this->name = $this->parent->name.'Item';
            } elseif ($this->type !== SimpleType::ARRAY->value) {
                throw new InvalidArgumentException('$name must be defined if the schema or parent schema is not of type `array`.');
            }
        } else {
            $this->name = $name;
        }
    }

    public function getDocTypeString(bool $notNull = false): string
    {
        $type = $this->type;
        if ($this->items) {
            $type = "{$this->items->getDocTypeString(! $this->isNullable())}[]";
            // Sometimes the array itself isn't nullable, but its items are
            if (! $this->isNullable() && $this->items->isNullable() && $type[0] === '?') {
                $type = substr($type, 1);
            } elseif ($this->isNullable() && ! $this->items->isNullable()) {  // And sometimes the array is nullable, but its items aren't
                $type = "{$type}|null";
            }
        } else {
            $type = parent::getDocTypeString();
        }

        return $type;
    }

    public function isNullable(): bool
    {
        if (is_array($this->parent?->required)) {
            return ! in_array($this->rawName, $this->parent->required);
        }

        return $this->nullable;
    }

    public function equalsOpenApiSchema(OpenApiSchema $other): bool
    {
        if ($this->properties) {
            $propNames = array_keys($this->properties);
            $otherPropNames = array_keys($other->properties);
            sort($propNames);
            sort($otherPropNames);

            if ($propNames !== $otherPropNames) {
                return false;
            }

            // We don't recursively run equalsOpenApiSchema on the schema's properties,
            // because sometimes this check is being run on a schema while its properties
            // are still being parsed

            $reqs = $this->required ?? [];
            $otherReqs = $other->required ?? [];
            sort($reqs);
            sort($otherReqs);
            if ($reqs !== $otherReqs) {
                return false;
            }
        } elseif ($this->items) {
            if (! $other->items) {
                return false;
            }

            $sameItems = $this->items->type === $other->items->title;
            if (! $sameItems) {
                return false;
            }
        }

        $baseConditions = $this->description === $other->description
            && $this->nullable === $other->nullable;

        return $baseConditions;
    }
}
