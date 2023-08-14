<?php

namespace Crescat\SaloonSdkGenerator\Commands;

use Crescat\SaloonSdkGenerator\CodeGenerator;
use Crescat\SaloonSdkGenerator\Data\Generator\GeneratedCode;
use Crescat\SaloonSdkGenerator\Parsers\Factory;
use Crescat\SaloonSdkGenerator\Utils;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use LaravelZero\Framework\Commands\Command;
use Nette\PhpGenerator\PhpFile;
use ZipArchive;

class GenerateSdk extends Command
{
    protected $signature = 'generate:sdk
                            {path : Path to the API specification file to generate the SDK from, must be a local file}
                            {--type=postman : The type of API Specification (postman, openapi, apiblueprint)}
                            {--name=Unnamed : The name of the SDK}
                            {--namespace=App\\Sdk : The root namespace of the SDK}
                            {--output=./build : The output path where the code will be created, will be created if it does not exist.}
                            {--force : Force overwriting existing files}
                            {--dry : Dry run, will only show the files to be generated, does not create or modify any files.}
                            {--zip : Generate a zip archive containing all the files}';

    protected array $usedClassNames = [];

    protected array $collectionQueue = [];

    public function handle(): void
    {
        $inputPath = $this->argument('path');

        // TODO: Support remote URLs
        if (! file_exists($inputPath)) {
            $this->error("File not found: $inputPath");

            return;
        }

        $type = trim(strtolower($this->option('type')));

        $generator = new CodeGenerator(
            namespace: $this->option('namespace'),
            resourceNamespaceSuffix: 'Resource',
            requestNamespaceSuffix: 'Requests',
            dtoNamespaceSuffix: 'Dto',
            connectorName: $this->option('name'),
            outputFolder: $this->option('output') ?? './Generated',
            ignoredQueryParams: [
                'after',
                'order_by',
                'per_page',
            ]
        );

        $result = $generator->run(Factory::parse($type, $inputPath));

        if ($this->option('zip')) {
            $this->generateZipArchive($result);

            return;
        }

        $this->option('dry')
            ? $this->printGeneratedFiles($result)
            : $this->dumpGeneratedFiles($result);
    }

    protected function printGeneratedFiles(GeneratedCode $result): void
    {
        $this->title('Generated Files');

        $this->comment("\nConnector:");
        if ($result->connectorClass) {
            $this->line(Utils::formatNamespaceAndClass($result->connectorClass));
        }

        $this->comment("\nBase Resource:");
        if ($result->resourceBaseClass) {
            $this->line(Utils::formatNamespaceAndClass($result->resourceBaseClass));
        }

        $this->comment("\nResources:");
        foreach ($result->resourceClasses as $resourceClass) {
            $this->line(Utils::formatNamespaceAndClass($resourceClass));
        }

        $this->comment("\nRequests:");
        foreach ($result->requestClasses as $requestClass) {
            $this->line(Utils::formatNamespaceAndClass($requestClass));
        }
    }

    protected function dumpGeneratedFiles(GeneratedCode $result): void
    {

        $this->title('Generated Files');

        $this->comment("\nConnector:");
        if ($result->connectorClass) {
            $this->dumpToFile($result->connectorClass);
        }

        $this->comment("\nBase Resource:");
        if ($result->resourceBaseClass) {
            $this->dumpToFile($result->resourceBaseClass);
        }

        $this->comment("\nResources:");
        foreach ($result->resourceClasses as $resourceClass) {
            $this->dumpToFile($resourceClass);
        }

        $this->comment("\nRequests:");
        foreach ($result->requestClasses as $requestClass) {
            $this->dumpToFile($requestClass);
        }
    }

    protected function dumpToFile(PhpFile $file): void
    {

        // TODO: Cleanup this, brittle and will break if you change the namespace
        $wip = sprintf(
            '%s/%s/%s.php',
            $this->option('output'),
            str_replace($this->option('namespace'), '', Arr::first($file->getNamespaces())->getName()),
            Arr::first($file->getClasses())->getName(),
        );

        $filePath = Str::of($wip)->replace('\\', '/')->replace('//', '/')->toString();

        if (! file_exists(dirname($filePath))) {
            mkdir(dirname($filePath), recursive: true);
        }

        if (file_exists($filePath) && ! $this->option('force')) {
            $this->warn("- File already exists: $filePath");

            return;
        }

        $ok = file_put_contents($filePath, (string) $file);

        if ($ok === false) {
            $this->error("- Failed to write: $filePath");
        } else {
            $this->line("- Created: $filePath");
        }
    }

    protected function generateZipArchive(GeneratedCode $result): void
    {
        $zipFileName = $this->option('name').'_sdk.zip';
        $zipPath = $this->option('output').DIRECTORY_SEPARATOR.$zipFileName;

        if (! file_exists(dirname($zipPath))) {
            mkdir(dirname($zipPath), recursive: true);
        }

        if (file_exists($zipPath) && ! $this->option('force')) {
            $this->warn("- Zip archive already exists: $zipPath");

            return;
        }

        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->error("- Failed to create the ZIP archive: $zipPath");

            return;
        }

        $filesToZip = array_merge(
            [$result->connectorClass, $result->resourceBaseClass],
            $result->resourceClasses,
            $result->requestClasses
        );

        foreach ($filesToZip as $file) {
            $filePathInZip = str_replace('\\', '/', Arr::first($file->getNamespaces())->getName()).'/'.Arr::first($file->getClasses())->getName().'.php';
            $zip->addFromString($filePathInZip, (string) $file);
            $this->line("- Wrote file to ZIP: $filePathInZip");
        }

        $zip->close();

        $this->line("- Created zip archive: $zipPath");
    }
}
