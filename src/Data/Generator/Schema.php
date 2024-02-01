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
        public ?string $rawName = null,

        public ?Schema $parent = null,

        public ?Schema $items = null,
        public ?array $properties = [],
        public ?array $required = null,
    ) {
        if (is_null($name)) {
            if ($this->parent->type === SimpleType::ARRAY->value) {
                $this->name = $this->parent->name.'Item';
            } else {
                throw new InvalidArgumentException('$name must be defined if the parent schema is not of type `array`.');
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
            // And sometimes the array is nullable, but its items aren't
            } elseif ($this->isNullable() && ! $this->items->isNullable()) {
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
}
