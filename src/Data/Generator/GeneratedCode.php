<?php

declare(strict_types=1);

namespace Crescat\SaloonSdkGenerator\Data\Generator;

use Crescat\SaloonSdkGenerator\Contracts\FileHandler;
use Crescat\SaloonSdkGenerator\FileHandlers\BasicFileHandler;
use Nette\PhpGenerator\PhpFile;

class GeneratedCode
{
    /**
     * @param  array|PhpFile[]  $requestClasses
     * @param  array|PhpFile[]  $responseClasses
     * @param  array|PhpFile[]  $resourceClasses
     * @param  array|PhpFile[]  $dtoClasses
     */
    public function __construct(
        public Config $config,
        public array $requestClasses = [],
        public array $responseClasses = [],
        public array $resourceClasses = [],
        public array $dtoClasses = [],
        public ?PhpFile $connectorClass = null,
        public ?PhpFile $resourceBaseClass = null,
        public ?FileHandler $fileHandler = null,
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

        if ($this->resourceBaseClass) {
            $path = $this->fileHandler->baseResourcePath($this->resourceBaseClass);
            $this->dumpToFile($this->resourceBaseClass, $path);
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

        return $ok;
    }
}
