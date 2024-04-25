<?php

declare(strict_types=1);

namespace Crescat\SaloonSdkGenerator\Contracts;

use Crescat\SaloonSdkGenerator\Enums\SupportingFile;
use Nette\PhpGenerator\PhpFile;

interface FileHandler
{
    public function requestPath(PhpFile $file): string;

    public function responsePath(PhpFile $file): string;

    public function resourcePath(PhpFile $file): string;

    public function dtoPath(PhpFile $file): string;

    public function baseDtoPath(PhpFile $file): string;

    public function baseRequestPath(PhpFile $file): string;

    public function baseResponsePath(PhpFile $file): string;

    public function baseResourcePath(PhpFile $file): string;

    public function connectorPath(PhpFile $file): string;

    public function supportingFilePath(SupportingFile $type, PhpFile $file): string;
}
