<?php

declare(strict_types=1);

namespace Crescat\SaloonSdkGenerator\Data\Generator;

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
        public bool $isResponse = false,
        // Having this default to false conflicts with the OpenAPI spec (where the default for
        // additionalProperties is true), but in reality most of the time, most schemas don't
        // have additional properties
        public Schema|bool $additionalProperties = false,
        ?string $name = null,

        public ?Schema $parent = null,
        // This is the name of the property in the parent schema that points to this schema
        public ?string $parentPropName = null,

        public ?Schema $items = null,
        public ?array $properties = [],
        public ?array $required = null,
    ) {
        if (is_null($name)) {
            if ($this->parent->type === SimpleType::ARRAY->value) {
                $this->name = $this->parent->name.' item';
            } else {
                throw new InvalidArgumentException('$name must be defined if the parent schema is not of type `array`.');
            }
        } else {
            $this->name = $name;
        }
    }

    public function getDocTypeString(): string
    {
        $type = $this->type;
        if ($this->items) {
            $type = "{$this->items->getDocTypeString()}[]";
        } else {
            $type = parent::getDocTypeString();
        }

        return $type;
    }

    public function isNullable(): bool
    {
        if (is_array($this->parent?->required)) {
            return ! in_array($this->parentPropName, $this->parent->required);
        }

        return $this->nullable;
    }
}
