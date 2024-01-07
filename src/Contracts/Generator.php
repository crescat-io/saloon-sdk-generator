<?php

namespace Crescat\SaloonSdkGenerator\Contracts;

use Crescat\SaloonSdkGenerator\Data\Generator\ApiSpecification;
use Crescat\SaloonSdkGenerator\Data\Generator\Config;
use Nette\PhpGenerator\PhpFile;

interface Generator
{
    public function __construct(?Config $config = null);

    public function setConfig(Config $config): static;

    /**
     * @return PhpFile|PhpFile[]
     */
    public function generate(ApiSpecification $specification): PhpFile|array;
}
