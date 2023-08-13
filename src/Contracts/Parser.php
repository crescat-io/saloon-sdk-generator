<?php

namespace Crescat\SaloonSdkGenerator\Contracts;

use Crescat\SaloonSdkGenerator\Data\Generator\Endpoints;

interface Parser
{
    public function parse(): Endpoints;
}
