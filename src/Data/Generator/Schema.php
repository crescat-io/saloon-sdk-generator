<?php

namespace Crescat\SaloonSdkGenerator\Data\Generator;

use InvalidArgumentException;

class Schema
{
    /**
     * @param  Schema[]  $properties
     */
    public function __construct(
        public string $type,
        public string $name,
        public ?string $description,
        public ?bool $nullable = false,
        public bool $isResponse = false,

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
}
