<?php

declare(strict_types=1);

namespace Crescat\SaloonSdkGenerator\Enums;

enum SupportingFile: string
{
    case CONTRACT = 'Contracts';
    case EXCEPTION = 'Exceptions';
    case TRAIT = 'Traits';
}
