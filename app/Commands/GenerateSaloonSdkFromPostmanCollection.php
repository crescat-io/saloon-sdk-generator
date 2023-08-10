<?php

/** @noinspection PhpMultipleClassDeclarationsInspection */

namespace App\Commands;

use App\Data\Saloon\GeneratedFile;
use App\Generator;
use LaravelZero\Framework\Commands\Command;

class GenerateSaloonSdkFromPostmanCollection extends Command
{
    protected $signature = 'generate:postman {path} {--force}';

    protected array $usedClassNames = [];

    protected array $collectionQueue = [];

    public function handle(): void
    {
        $path = $this->argument('path');

        if (! file_exists($path)) {
            $this->error("File not found: $path");

            return;
        }

        $raw = file_get_contents($path);

        if (! $raw) {
            $this->error('File is empty');

            return;
        }

        collect(Generator::fromJson($raw))
            ->groupBy(fn (GeneratedFile $file) => $file->collectionName)
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
        $folder = "./src/Compiled/Requests/{$file->collectionName}/";
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
