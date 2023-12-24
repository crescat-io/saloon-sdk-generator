<?php

namespace Crescat\SaloonSdkGenerator\Data\Generator;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Nette\PhpGenerator\PhpFile;

class GeneratedCode
{
    /**
     * @param  array|PhpFile[]  $requestClasses
     * @param  array|PhpFile[]  $resourceClasses
     * @param  array|PhpFile[]  $dtoClasses
     */
    public function __construct(
        public Config $config,
        public array $requestClasses = [],
        public array $resourceClasses = [],
        public array $dtoClasses = [],
        public ?PhpFile $connectorClass = null,
        public ?PhpFile $resourceBaseClass = null,
    ) {
    }

    /**
     * Dump all generated files to disk.
     */
    public function dumpFiles(): void
    {
        if ($this->connectorClass) {
            $this->dumpToFile($this->connectorClass);
        }

        if ($this->resourceBaseClass) {
            $this->dumpToFile($this->resourceBaseClass);
        }

        foreach ($this->resourceClasses as $resourceClass) {
            $this->dumpToFile($resourceClass);
        }

        foreach ($this->requestClasses as $requestClass) {
            $this->dumpToFile($requestClass);
        }
    }

    /**
     * Dump a generated file to disk. Return the file creation status (from file_put_contents).
     */
    public function dumpToFile(PhpFile $file): bool
    {
        $filePath = $this->outputPath($file);
        if (file_exists($filePath) && ! $this->config->force) {
            return true;
        }

        $ok = file_put_contents($filePath, (string) $file);

        return $ok;
    }

    /**
     * Generate the output path for a given generated file. If necessary, create
     * the directory structure.
     */
    public function outputPath(PhpFile $file): string
    {
        // TODO: Cleanup this, brittle and will break if you change the namespace
        $wip = sprintf(
            '%s/%s/%s.php',
            $this->config->outputDir,
            str_replace($this->config->namespace, '', Arr::first($file->getNamespaces())->getName()),
            Arr::first($file->getClasses())->getName(),
        );

        $filePath = Str::of($wip)->replace('\\', '/')->replace('//', '/')->toString();

        if (! file_exists(dirname($filePath))) {
            mkdir(dirname($filePath), recursive: true);
        }

        return $filePath;
    }
}
