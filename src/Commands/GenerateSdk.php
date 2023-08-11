<?php

namespace Crescat\SaloonSdkGenerator\Commands;

use cebe\openapi\Reader;
use cebe\openapi\ReferenceContext;
use Crescat\SaloonSdkGenerator\CodeGenerator;
use Crescat\SaloonSdkGenerator\Data\Postman\PostmanCollection;
use Crescat\SaloonSdkGenerator\Data\Saloon\GeneratedFile;
use Crescat\SaloonSdkGenerator\Parsers\OpenApiParser;
use Crescat\SaloonSdkGenerator\Parsers\PostmanCollectionParser;
use Crescat\SaloonSdkGenerator\Utils;
use LaravelZero\Framework\Commands\Command;

class GenerateSdk extends Command
{
    protected $signature = 'generate:sdk {path} {--type=postman} {--output=./build} {--force}';

    protected array $usedClassNames = [];

    protected array $collectionQueue = [];

    public function handle(): void
    {
        $inputPath = $this->argument('path');

        if (! file_exists($inputPath)) {
            $this->error("File not found: $inputPath");

            return;
        }

        $raw = file_get_contents($inputPath);

        if (! $raw) {
            $this->error('File is empty');

            return;
        }

        $type = trim(strtolower($this->option('type')));
        $parser = match ($type) {
            // TODO: improve "type guessing", should also support remote urls
            'openapi' => new OpenApiParser(
                openApi: str_ends_with($inputPath, '.json')
                    ? Reader::readFromJsonFile(fileName: realpath($inputPath), resolveReferences: ReferenceContext::RESOLVE_MODE_ALL)
                    : Reader::readFromYamlFile(fileName: realpath($inputPath), resolveReferences: ReferenceContext::RESOLVE_MODE_ALL)
            ),
            'postman' => new PostmanCollectionParser(
                PostmanCollection::fromJson(json_decode($raw, true))
            )
        };

        $endpoints = $parser->parse();

        //        $groupedByCollection = collect($endpoints)->groupBy(fn (Endpoint $endpoint) => $endpoint->collection);
        //
        //        foreach ($groupedByCollection as $collection => $items) {
        //
        //            $this->newLine();
        //            $this->comment("$collection");
        //
        //            foreach ($items as $item) {
        //                $prefix = strtoupper(str_pad($item->method, 6, ' ', STR_PAD_LEFT));
        //                $path = $item->pathAsString();
        //                $this->line("{$prefix}: {$path}");
        //            }
        //        }
        //
        //        return;

        $generator = new CodeGenerator(
            namespace: "App\Sdk",
            resourceNamespaceSuffix: 'Resource',
            requestNamespaceSuffix: 'Requests',
            dtoNamespaceSuffix: 'Dto',
            connectorName: 'Connector',
            outputFolder: $this->option('output') ?? './Generated',
            ignoredQueryParams: [
                'after',
                'order_by',
                'per_page',
            ]
        );

        $result = $generator->run($parser);

        $this->title('Generated Files');
        $this->comment("\nConnector:");
        if ($result->connectorClass) {
            $this->line(Utils::formatNamespaceAndClass($result->connectorClass));
        }

        $this->comment("\nResources:");
        foreach ($result->resourceClasses as $resourceClass) {
            $this->line(Utils::formatNamespaceAndClass($resourceClass));
        }

        $this->comment("\nRequests:");
        foreach ($result->requestClasses as $requestClass) {
            $this->line(Utils::formatNamespaceAndClass($requestClass));
        }

        //        collect(Generator::fromJson($raw))
        //            ->groupBy(fn (GeneratedFile $file) => $file->collectionName)
        //            ->sort()
        //            ->each(function ($files, $folder) {
        //
        //                $this->info($folder);
        //                foreach ($files as $file) {
        //                    $this->dumpToFile($file);
        //                }
        //            });
    }

    protected function dumpToFile(GeneratedFile $file)
    {
        // TODO: Folder name should be implied based on namespace
        $out = $this->option('output');
        $folder = "$out/Requests/{$file->collectionName}/";
        $className = $file->className;
        $filePath = "{$folder}{$className}.php";

        if (! file_exists($folder)) {
            mkdir($folder, recursive: true);
        }

        if (file_exists($filePath) && ! $this->option('force')) {
            $this->warn("- File already exists: $filePath");

            return;
        }

        $ok = file_put_contents($filePath, (string) $file->phpFile);

        if ($ok === false) {
            $this->error("- Failed to write: $filePath");
        } else {
            $this->line("- Created: $filePath");
        }
    }
}
