<?php

namespace Crescat\SaloonSdkGenerator\Data\Generator;

use InvalidArgumentException;

class Schema extends Parameter
{
    public array|bool|null $required;

    /**
     * @param  Schema[]  $properties
     * @param  string[]|bool  $required
     */
    public function __construct(
        public string $type,
        public bool $nullable,
        public string $name,
        public ?string $description,

        public ?Schema $items = null,
        public ?array $properties = [],
        array|bool|null $required = null,
    ) {
        // Object schemas must have a list of required properties, and array schemas must have a true/false required value
        if (is_bool($required) && $this->properties) {
            throw new InvalidArgumentException('The required parameter must be a string array if the properties parameter is defined.');
        } elseif (is_array($required) && $this->items) {
            throw new InvalidArgumentException('The required parameter must be a boolean if the items parameter is defined.');
        }

        $this->required = $required;
    }

    public function getDocTypeString(): string
    {
        $type = $this->type;
        if ($this->items) {
            $type = "{$this->items->getDocTypeString()}[]";
        }
        if ($this->nullable) {
            $type = "null|{$type}";
        }

        return $type;
    }
}
