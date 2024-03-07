<?php

namespace Crescat\SaloonSdkGenerator;

use Crescat\SaloonSdkGenerator\Contracts\Generator as GeneratorContract;
use Crescat\SaloonSdkGenerator\Data\Generator\Config;

abstract class Generator implements GeneratorContract
{
    public function __construct(protected ?Config $config = null)
    {

    }

    public function setConfig(?Config $config): static
    {
        $this->config = $config;

        return $this;
    }
}
