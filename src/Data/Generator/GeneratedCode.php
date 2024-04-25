<?php

declare(strict_types=1);

namespace Crescat\SaloonSdkGenerator\Data\Generator;

use Crescat\SaloonSdkGenerator\Contracts\FileHandler;
use Crescat\SaloonSdkGenerator\Enums\SupportingFile;
use Crescat\SaloonSdkGenerator\FileHandlers\BasicFileHandler;
use Nette\PhpGenerator\PhpFile;

class GeneratedCode
{
    /**
     * @param  PhpFile[]  $requestClasses
     * @param  PhpFile[]  $responseClasses
     * @param  PhpFile[]  $resourceClasses
     * @param  PhpFile[]  $dtoClasses
     * @param  PhpFile[]  $baseResponseClasses
     * @param  PhpFile[]  $supportingFiles
     */
    public function __construct(
        public Config $config,
        public array $requestClasses = [],
        public array $responseClasses = [],
        public array $resourceClasses = [],
        public array $dtoClasses = [],
        public ?PhpFile $connectorClass = null,
        public ?PhpFile $requestBaseClass = null,
        public ?array $responseBaseClasses = null,
        public ?PhpFile $resourceBaseClass = null,
        public ?PhpFile $dtoBaseClass = null,
        public ?FileHandler $fileHandler = null,
        public array $supportingFiles = [],
    ) {
        $this->fileHandler ??= new BasicFileHandler($config);
    }

    /**
     * Dump all generated files to disk.
     */
    public function dumpFiles(): void
    {
        foreach ($this->requestClasses as $requestClass) {
            $path = $this->fileHandler->requestPath($requestClass);
            $this->dumpToFile($requestClass, $path);
        }

        foreach ($this->responseClasses as $responseClass) {
            $path = $this->fileHandler->responsePath($responseClass);
            $this->dumpToFile($responseClass, $path);
        }

        foreach ($this->resourceClasses as $resourceClass) {
            $path = $this->fileHandler->resourcePath($resourceClass);
            $this->dumpToFile($resourceClass, $path);
        }

        foreach ($this->dtoClasses as $dtoClass) {
            $path = $this->fileHandler->dtoPath($dtoClass);
            $this->dumpToFile($dtoClass, $path);
        }

        if ($this->connectorClass) {
            $path = $this->fileHandler->connectorPath($this->connectorClass);
            $this->dumpToFile($this->connectorClass, $path);
        }

        if ($this->requestBaseClass) {
            $path = $this->fileHandler->baseRequestPath($this->requestBaseClass);
            $this->dumpToFile($this->requestBaseClass, $path);
        }

        foreach ($this->responseBaseClasses as $responseBaseClass) {
            $path = $this->fileHandler->baseResponsePath($responseBaseClass);
            $this->dumpToFile($responseBaseClass, $path);
        }

        if ($this->resourceBaseClass) {
            $path = $this->fileHandler->baseResourcePath($this->resourceBaseClass);
            $this->dumpToFile($this->resourceBaseClass, $path);
        }

        if ($this->dtoBaseClass) {
            $path = $this->fileHandler->baseDtoPath($this->dtoBaseClass);
            $this->dumpToFile($this->dtoBaseClass, $path);
        }

        foreach ($this->supportingFiles as $type => $supportingFiles) {
            $_type = SupportingFile::tryFrom($type);
            foreach ($supportingFiles as $file) {
                $path = $this->fileHandler->supportingFilePath($_type, $file);
                $this->dumpToFile($file, $path);
            }
        }

    }

    /**
     * Dump a generated file to disk. Return the file creation status (from file_put_contents).
     */
    public function dumpToFile(PhpFile $file, string $filePath): bool
    {
        if (file_exists($filePath) && ! $this->config->force) {
            return true;
        }
        if (! file_exists(dirname($filePath))) {
            mkdir(dirname($filePath), recursive: true);
        }

        $ok = file_put_contents($filePath, (string) $file);

        return (bool) $ok;
    }
}
