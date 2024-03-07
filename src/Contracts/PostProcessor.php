<?php

namespace Crescat\SaloonSdkGenerator\Contracts;

use Crescat\SaloonSdkGenerator\Data\Generator\ApiSpecification;
use Crescat\SaloonSdkGenerator\Data\Generator\Config;
use Crescat\SaloonSdkGenerator\Data\Generator\GeneratedCode;
use Nette\PhpGenerator\PhpFile;

/**
 * @PostProcessor
 *
 * A post processor runs after the main generators have run.
 * Useful for generating additional files based on the generated code.
 * For example, a test suite, or for running formatting tools on the generated code.
 */
interface PostProcessor
{
    /**
     * @return PhpFile|PhpFile[]|null
     */
    public function process(
        Config $config,
        ApiSpecification $specification,
        GeneratedCode $generatedCode,
    ): PhpFile|array|null;
}
