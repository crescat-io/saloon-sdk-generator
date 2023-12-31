<?php

declare(strict_types=1);

namespace Crescat\SaloonSdkGenerator\Data\Generator;

class Parameter
{
    public function __construct(
        public string $type,
        public bool $nullable,
        public string $name,
        public ?string $description = null
    ) {
    }

    public function getDocTypeString(): string
    {
        $nullString = str_contains($this->type, '|') ? 'null|' : '?';

        return $this->isNullable() ? "{$nullString}{$this->type}" : $this->type;
    }

    public function isNullable(): bool
    {
        return $this->nullable;
    }
}
