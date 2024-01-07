<?php

declare(strict_types=1);

namespace Crescat\SaloonSdkGenerator\Data\Generator;

use Crescat\SaloonSdkGenerator\Enums\SimpleType;
use Crescat\SaloonSdkGenerator\Helpers\NameHelper;

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
        $type = $this->type;
        if (! SimpleType::tryFrom($type)) {
            $type = NameHelper::safeClassName($type);
        }
        $nullString = str_contains($type, '|') ? 'null|' : '?';

        return $this->isNullable() ? "{$nullString}{$type}" : $type;
    }

    public function isNullable(): bool
    {
        return $this->nullable;
    }
}
