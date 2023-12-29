<?php

declare(strict_types=1);

namespace Crescat\SaloonSdkGenerator;

use Crescat\SaloonSdkGenerator\Contracts\Deserializable;
use Crescat\SaloonSdkGenerator\Traits\Deserializes;
use Crescat\SaloonSdkGenerator\Traits\HasArrayableAttributes;

abstract class BaseDto implements Deserializable
{
    use Deserializes;
    use HasArrayableAttributes;
}
