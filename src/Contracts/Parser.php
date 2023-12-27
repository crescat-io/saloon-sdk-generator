<?php

declare(strict_types=1);

namespace Crescat\SaloonSdkGenerator\Contracts;

use Crescat\SaloonSdkGenerator\Data\Generator\ApiSpecification;

interface Parser
{
    public function parse(): ApiSpecification;
}
