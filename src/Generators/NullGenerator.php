<?php

declare(strict_types=1);

namespace Crescat\SaloonSdkGenerator\Generators;

use Crescat\SaloonSdkGenerator\Data\Generator\ApiSpecification;
use Crescat\SaloonSdkGenerator\Generator;
use Nette\PhpGenerator\PhpFile;

/**
 * Class NullGenerator
 *
 * This generator doesn't produce anything. It is used to disable file generator for
 * a particular file category (connectors, resources, etc).
 */
class NullGenerator extends Generator
{
    public function generate(ApiSpecification $specification): PhpFile|array|null
    {
        return null;
    }
}
