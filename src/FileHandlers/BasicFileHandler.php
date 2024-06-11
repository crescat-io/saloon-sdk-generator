<?php

declare(strict_types=1);

namespace Crescat\SaloonSdkGenerator\FileHandlers;

use Crescat\SaloonSdkGenerator\Enums\SupportingFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Nette\PhpGenerator\PhpFile;

class BasicFileHandler extends AbstractFileHandler
{
    public function requestPath(PhpFile $file): string
    {
        return $this->outputPath($file);
    }

    public function responsePath(PhpFile $file): string
    {
        return $this->outputPath($file);
    }

    public function resourcePath(PhpFile $file): string
    {
        return $this->outputPath($file);
    }

    public function dtoPath(PhpFile $file): string
    {
        return $this->outputPath($file);
    }

    public function baseResponsePath(PhpFile $file): string
    {
        return $this->baseOutputPath($file);
    }

    public function baseRequestPath(PhpFile $file): string
    {
        return $this->baseOutputPath($file);
    }

    public function baseDtoPath(PhpFile $file): string
    {
        return $this->baseOutputPath($file);
    }

    public function baseResourcePath(PhpFile $file): string
    {
        return $this->baseOutputPath($file);
    }

    public function connectorPath(PhpFile $file): string
    {
        return $this->baseOutputPath($file);
    }

    public function supportingFilePath(SupportingFile $type, PhpFile $file): string
    {
        return $this->baseOutputPath($file, $type->value, true);
    }

    protected function outputPath(PhpFile $file): string
    {
        $components = [
            $this->config->outputDir,
            str_replace($this->config->namespace, '', Arr::first($file->getNamespaces())->getName()),
            Arr::first($file->getClasses())->getName(),
        ];

        return $this->buildPath($components);
    }

    protected function baseOutputPath(PhpFile $file, ?string $subPath = ''): string
    {
        $components = [
            $this->config->outputDir,
            $subPath,
            Arr::first($file->getClasses())->getName(),
        ];

        return $this->buildPath($components);
    }

    protected function buildPath(array $components): string
    {
        $path = implode('/', $components).'.php';

        return Str::of($path)->replace('\\', '/')->replace('//', '/')->toString();
    }
}
