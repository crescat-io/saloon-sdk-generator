<?php

declare(strict_types=1);

namespace Crescat\SaloonSdkGenerator\Enums;

enum SimpleType: string
{
    case STRING = 'string';
    case INTEGER = 'int';
    case FLOAT = 'float';
    case BOOLEAN = 'bool';
    case ARRAY = 'array';
    case DATE = 'date';
    case DATETIME = 'datetime';
    case MIXED = 'mixed';
    case NULL = 'null';

    public static function isScalar(string $type): bool
    {
        $enumType = SimpleType::tryFrom($type);

        return match ($enumType) {
            self::STRING, self::INTEGER, self::FLOAT, self::BOOLEAN, self::NULL => true,
            default => false,
        };
    }
}
