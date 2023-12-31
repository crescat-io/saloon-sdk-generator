<?php

declare(strict_types=1);

namespace Crescat\SaloonSdkGenerator\Enums;

enum SimpleType: string
{
    case STRING = 'string';
    case INTEGER = 'int';
    case NUMBER = 'number';
    case BOOLEAN = 'bool';
    case ARRAY = 'array';
    case DATE = 'date';
    case DATETIME = 'datetime';
    case MIXED = 'mixed';
    case NULL = 'null';
}
