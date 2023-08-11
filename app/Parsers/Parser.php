<?php

namespace App\Parsers;

use App\Data\Generator\Endpoint;

interface Parser
{
    /**
     * @return Endpoint[]
     */
    public function parse(): array;
}
