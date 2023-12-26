<?php

namespace Crescat\SaloonSdkGenerator\Contracts;

use Nette\PhpGenerator\PhpFile;

interface FileHandler
{
    public function requestPath(PhpFile $file): string;

    public function responsePath(PhpFile $file): string;

    public function resourcePath(PhpFile $file): string;

    public function dtoPath(PhpFile $file): string;

    public function baseResourcePath(PhpFile $file): string;

    public function connectorPath(PhpFile $file): string;
}
