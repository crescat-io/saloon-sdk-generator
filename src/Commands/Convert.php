<?php

namespace Crescat\SaloonSdkGenerator\Commands;

use Crescat\SaloonSdkGenerator\Converters\OpenApiConverter;
use Crescat\SaloonSdkGenerator\Exceptions\ConversionFailedException;
use LaravelZero\Framework\Commands\Command;

class Convert extends Command
{
    protected $signature = 'convert {input} {output?}';

    protected $description = 'OpenAPI Only - Converts an old API spec to a newer version if possible with https://converter.swagger.io';

    public function handle(): void
    {
        $inputPath = $this->argument('input');

        if (! file_exists($inputPath)) {
            $this->error("File not found: $inputPath");

            return;
        }

        $content = file_get_contents($inputPath);

        $outputPath = $this->argument('output') ?? str_replace('.json', '.converted.json', $inputPath);

        $confirmed = $this->confirm("Will be converting the following files: \n > OLD:  $inputPath \n > NEW:  $outputPath\n, Do you want to continue?");

        if (! $confirmed) {
            return;
        }

        try {

            $this->comment('Starting conversion, please wait...');
            $output = OpenApiConverter::convert($content);
            $this->info('Converted successfully');

            file_put_contents($outputPath, $output);
            $this->comment("Saved to $outputPath");
        } catch (ConversionFailedException $e) {
            $this->error($e->getMessage());

            return;
        }

    }
}
