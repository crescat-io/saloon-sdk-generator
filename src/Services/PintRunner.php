<?php

namespace Crescat\SaloonSdkGenerator\Services;

use Symfony\Component\Console\Output\OutputInterface;

class PintRunner
{
    public function run(string $outputDir, OutputInterface $output): void
    {
        $pintPath = "{$outputDir}/vendor/bin/pint";

        if (! file_exists($pintPath)) {
            $output->writeln('<comment>Pint not found. Skipping code formatting. Run "composer require --dev laravel/pint" to enable.</comment>');

            return;
        }

        $output->writeln('- Running Pint...');

        $directories = collect(['src', 'tests', 'config', 'Factories'])
            ->map(fn ($dir) => "{$outputDir}/{$dir}")
            ->filter(fn ($dir) => is_dir($dir))
            ->implode(' ');

        if (empty($directories)) {
            return;
        }

        $descriptorSpec = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open("{$pintPath} {$directories} -q", $descriptorSpec, $pipes, $outputDir);

        if (! is_resource($process)) {
            $output->writeln('<comment>Failed to start Pint process.</comment>');

            return;
        }

        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            $output->writeln("<comment>Pint formatting failed ({$exitCode}).\n{$stderr}</comment>");
        } else {
            $output->writeln('- Code formatted successfully.');
        }
    }
}
