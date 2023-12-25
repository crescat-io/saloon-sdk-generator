<?php

namespace Crescat\SaloonSdkGenerator\Contracts;

use Nette\PhpGenerator\PhpFile;

interface FileHandler
{
    public function baseResourcePath(PhpFile $file): string;

    public function connectorPath(PhpFile $file): string;

    public function resourcePath(PhpFile $file): string;

    public function requestPath(PhpFile $file): string;
}
