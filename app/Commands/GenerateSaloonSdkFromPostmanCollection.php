<?php

/** @noinspection PhpMultipleClassDeclarationsInspection */

namespace App\Commands;

use App\Data\Saloon\GeneratedFile;
use App\Generator;
use App\Parsers\OpenApiParser;
use cebe\openapi\Reader;
use cebe\openapi\ReferenceContext;
use LaravelZero\Framework\Commands\Command;

class GenerateSaloonSdkFromPostmanCollection extends Command
{
    protected $signature = 'generate:postman {path} {--output=./build} {--force}';

    protected array $usedClassNames = [];

    protected array $collectionQueue = [];

    public function handle(): void
    {
        $path = $this->argument('path');

        if (!file_exists($path)) {
            $this->error("File not found: $path");

            return;
        }

        $raw = file_get_contents($path);

        if (!$raw) {
            $this->error('File is empty');

            return;
        }


//        $parser = new PostmanCollectionParser(
//            PostmanCollection::fromJson(json_decode($raw,true))
//        );
//
//        $parser = new OpenApiParser(
//            Reader::readFromYamlFile(
//                fileName: base_path("tests/Samples/fiken.yml"),
//                resolveReferences: ReferenceContext::RESOLVE_MODE_ALL
//            )
//        );

        // NOTE: This is a swagger spec, which is older, but very similar, should be fine
        $parser = new OpenApiParser(
            Reader::readFromJsonFile(
                fileName: base_path("tests/Samples/tripletex.json"),
                resolveReferences: ReferenceContext::RESOLVE_MODE_ALL
            )
        );
        $endpoints = $parser->parse();

        dd($endpoints);


        collect(Generator::fromJson($raw))
            ->groupBy(fn(GeneratedFile $file) => $file->collectionName)
            ->sort()
            ->each(function ($files, $folder) {

                $this->info($folder);
                foreach ($files as $file) {
                    $this->dumpToFile($file);
                }
            });
    }

    protected function dumpToFile(GeneratedFile $file)
    {
        // TODO: Folder name should be implied based on namespace
        $out = $this->option('output');
        $folder = "$out/Requests/{$file->collectionName}/";
        $className = $file->className;
        $filePath = "{$folder}{$className}.php";

        if (!file_exists($folder)) {
            mkdir($folder, recursive: true);
        }

        if (file_exists($filePath) && !$this->option('force')) {
            $this->warn("- File already exists: $filePath");

            return;
        }

        $ok = file_put_contents($filePath, (string)$file->phpFile);

        if ($ok === false) {
            $this->error("- Failed to write: $filePath");
        } else {
            $this->line("- Created: $filePath");
        }
    }
}
