<?php

declare(strict_types=1);

namespace Crescat\SaloonSdkGenerator\Data\Generator;

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
        public bool $isResponse = false,
        // Having this default to false conflicts with the OpenAPI spec (where the default for
        // additionalProperties is true), but in reality most of the time, most schemas don't
        // have additional properties
        public Schema|bool $additionalProperties = false,
        ?string $name = null,

        public readonly ?Schema $parent = null,
        // This is the name of the property in the parent schema that points to this schema
        public readonly ?string $parentPropName = null,

        public ?Schema $items = null,
        public ?array $properties = [],
        public ?array $required = [],
    ) {
        if (is_null($name)) {
            if ($this->parent->type === 'array') {
                $this->name = $this->parent->name.'Item';
            } else {
                throw new InvalidArgumentException('$name must be defined if the parent schema is not of type `array`.');
            }
        } else {
            $this->name = $name;
        }
    }

    public function getDocTypeString(bool $required = false): string
    {
        $type = $this->type;
        if ($this->items) {
            $type = "{$this->items->getDocTypeString()}[]";
        }
        if ($this->nullable && ! $required) {
            $type = "null|{$type}";
        }

        return $type;
    }

    public function isNullable(): bool
    {
        if (is_array($this->parent?->required) && count($this->parent->required) > 0) {
            return ! in_array($this->parentPropName, $this->parent->required);
        }

        return $this->nullable;
    }
}
