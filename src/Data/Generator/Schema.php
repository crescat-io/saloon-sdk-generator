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
        ?string $name = null,
        public bool $nullable = false,
        public bool $isResponse = false,

        public readonly ?Schema $parent = null,
        public ?Schema $items = null,
        public ?array $properties = [],
        public ?array $required = null,
    ) {
        // Object schemas must have a list of required properties
        if (is_null($this->required) && $this->properties) {
            throw new InvalidArgumentException('The required parameter must be a string array if the properties parameter is defined.');
        } elseif (! is_null($this->required) && ! $this->properties) {
            throw new InvalidArgumentException('The required parameter cannot be an array if the properties parameter is not defined.');
        }

        if (is_null($name)) {
            if ($this->parent->type === 'array') {
                $this->name = $this->parent->name.'Item';
            } else {
                throw new InvalidArgumentException('The name parameter must be defined if the parent schema is not of type `array`.');
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
        if (is_array($this->parent?->required)) {
            return ! in_array($this->name, $this->parent->required);
        }

        return $this->nullable;
    }
}
