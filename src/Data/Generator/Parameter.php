<?php

declare(strict_types=1);

namespace Crescat\SaloonSdkGenerator\Data\Generator;

use Crescat\SaloonSdkGenerator\Helpers\NameHelper;
use Crescat\SaloonSdkGenerator\Helpers\Utils;

class Parameter
{
    public string $name;

    public ?string $rawName;

    public function __construct(
        public string $type,
        public bool $nullable,
        string $name,
        public ?string $description = null
    ) {
        $this->name = NameHelper::normalize($name);
        $this->rawName = $name;
    }

    /**
     * Generate a docstring for this parameter.
     *
     * @param  bool  $notNull  If true, the parameter will be forced to be marked non-null
     */
    public function getDocTypeString(bool $notNull = false): string
    {
        $type = $this->type;
        if (! Utils::isBuiltInType($type)) {
            $type = NameHelper::safeClassName($type);
        } elseif ($type === 'DateTime') {
            $type = '\DateTimeInterface';
        }
        $nullString = str_contains($type, '|') ? 'null|' : '?';

        return $this->isNullable() && ! $notNull ? "{$nullString}{$type}" : $type;
    }

    public function isNullable(): bool
    {
        return $this->nullable;
    }
}
