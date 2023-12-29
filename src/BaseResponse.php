<?php

declare(strict_types=1);

namespace Crescat\SaloonSdkGenerator;

use Crescat\SaloonSdkGenerator\Contracts\Deserializable;
use Crescat\SaloonSdkGenerator\Traits\Deserializes;
use Saloon\Contracts\DataObjects\WithResponse;
use Saloon\Traits\Responses\HasResponse;

abstract class BaseResponse implements Deserializable, WithResponse
{
    use Deserializes;
    use HasResponse;
}
