<?php

namespace Crescat\SaloonSdkGenerator\Contracts;

use Crescat\SaloonSdkGenerator\Data\Generator\Endpoint;

interface Parser
{
    /**
     * @return Endpoint[]
     */
    public function parse(): array;
}
