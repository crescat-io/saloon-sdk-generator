<?php

namespace Crescat\SaloonSdkGenerator\FileHandlers;

use Crescat\SaloonSdkGenerator\Contracts\FileHandler;
use Crescat\SaloonSdkGenerator\Data\Generator\Config;

abstract class AbstractFileHandler implements FileHandler
{
    public function __construct(protected Config $config)
    {
    }
}
