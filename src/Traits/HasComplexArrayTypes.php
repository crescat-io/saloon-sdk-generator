<?php

declare(strict_types=1);

namespace Crescat\SaloonSdkGenerator\Traits;

trait HasComplexArrayTypes
{
    /**
     * Override this to specify the item types (and key types, if necessary) of attributes.
     * Valid values either look like ['attributeName' => [SomeType::class]]
     * or ['attributeName' => [SimpleType::STRING|SimpleType::INTEGER, OtherType::class]].
     */
    protected static array $complexArrayTypes = [];

    protected static function getArrayType(string $attributeName): array|string
    {
        if (! array_key_exists($attributeName, static::$complexArrayTypes)) {
            return 'array';
        }

        return static::$complexArrayTypes[$attributeName];
    }
}
