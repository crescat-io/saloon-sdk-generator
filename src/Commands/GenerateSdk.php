<?php

namespace Crescat\SaloonSdkGenerator\Commands;

use Crescat\SaloonSdkGenerator\CodeGenerator;
use Crescat\SaloonSdkGenerator\Contracts\PostProcessor;
use Crescat\SaloonSdkGenerator\Data\Generator\ApiSpecification;
use Crescat\SaloonSdkGenerator\Data\Generator\Config;
use Crescat\SaloonSdkGenerator\Data\Generator\Endpoint;
use Crescat\SaloonSdkGenerator\Data\Generator\GeneratedCode;
use Crescat\SaloonSdkGenerator\Exceptions\ParserNotRegisteredException;
use Crescat\SaloonSdkGenerator\Factory;
use Crescat\SaloonSdkGenerator\Generators\ConfigPostProcessor;
use Crescat\SaloonSdkGenerator\Generators\FactoryGenerator;
use Crescat\SaloonSdkGenerator\Generators\PestTestGenerator;
use Crescat\SaloonSdkGenerator\Generators\ServiceProviderPostProcessor;
use Crescat\SaloonSdkGenerator\Generators\TestSetupPostProcessor;
use Crescat\SaloonSdkGenerator\Helpers\Utils;
use Crescat\SaloonSdkGenerator\Services\ComposerSetup;
use Crescat\SaloonSdkGenerator\Services\ConfigValuesService;
use Crescat\SaloonSdkGenerator\Services\PintRunner;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use LaravelZero\Framework\Commands\Command;
use Nette\PhpGenerator\PhpFile;
use RuntimeException;

class GenerateSdk extends Command
{
    protected $signature = 'generate:sdk
                            {spec : Path to the API specification file (local file or URL)}
                            {--type=openapi : The type of API Specification (postman, openapi)}
                            {--o|output=./output : Output directory for the generated SDK}
                            {--connector-name= : Name of the Connector class (e.g. "FikenConnector"). Config key is derived from this. Required when using --foundation}
                            {--skip-tests : Skip generating Pest tests}
                            {--skip-factories : Skip generating Faker factories}
                            {--foundation : Generate Laravel foundation files (config, service provider, test setup)}
                            {--base-url= : Default base URL for the API}
                            {--dry-run : Show what would be generated without writing files}
                            {--force : Overwrite existing files}
                            {--exclude-put-requests : Exclude PUT requests from the generated SDK}';

    protected $description = 'Generate an SDK based on an API specification file.';

    protected string $namespace;

    public function handle(): int
    {
        $specPath = $this->argument('spec');
        $outputDir = $this->option('output');

        try {
            $composerSetup = new ComposerSetup(rtrim($outputDir, '/').'/composer.json');
            $namespace = $this->namespace = $composerSetup->getNamespace();
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $generateTests = ! $this->option('skip-tests');
        $generateFactories = ! $this->option('skip-factories');
        $generateFoundation = $this->option('foundation');
        $connectorNameOption = $this->option('connector-name');
        $baseUrlOption = $this->option('base-url');
        $excludePut = $this->option('exclude-put-requests');

        if ($generateFoundation && $connectorNameOption === null) {
            $this->error('The --connector-name option is required when using --foundation');

            return self::FAILURE;
        }

        if ($generateFoundation) {
            $connectorName = $connectorNameOption;
            $baseUrl = $baseUrlOption;
        } else {
            $configValues = (new ConfigValuesService)->resolveFromExisting(
                $outputDir,
                connectorName: $connectorNameOption,
                baseUrl: $baseUrlOption,
            );

            if ($configValues === null) {
                $this->error('No existing SDK found in output directory. Use --foundation to generate a new SDK.');

                return self::FAILURE;
            }

            $connectorName = $configValues['connectorName'];
            $baseUrl = $configValues['baseUrl'];
        }

        $configKey = Config::deriveConfigKey($connectorName);

        if (! $this->isUrl($specPath) && ! file_exists($specPath)) {
            $this->error("Specification file not found: $specPath");

            return self::FAILURE;
        }

        $this->title('SDK Generator');
        $this->line("Spec: $specPath");
        $this->line("Output: $outputDir");
        $this->line("Namespace (from composer.json): $namespace");
        $this->line("Connector: $connectorName");
        $this->line("Config key: $configKey");
        $this->line('Tests: '.($generateTests ? 'Yes' : 'No'));
        $this->line('Factories: '.($generateFactories ? 'Yes' : 'No'));
        $this->line('Foundation: '.($generateFoundation ? 'Yes' : 'No'));
        $this->line('Exclude PUT: '.($excludePut ? 'Yes' : 'No'));

        if ($this->isUrl($specPath)) {
            $specPath = $this->downloadSpec($specPath);
        }

        $type = trim(strtolower($this->option('type')));

        try {
            $specification = Factory::parse($type, $specPath);
        } catch (ParserNotRegisteredException) {
            $this->error("No parser registered for --type='$type'");

            if (in_array($type, ['yml', 'yaml', 'json', 'xml'])) {
                $this->warn('Note: the --type option is used to specify the API Specification type (ex: openapi, postman), not the file format.');
            }

            $this->line('Available types: '.implode(', ', Factory::getRegisteredParserTypes()));

            return self::FAILURE;
        }

        if ($excludePut) {
            $this->excludePutRequests($specification);
        }

        $config = new Config(
            connectorName: $connectorName,
            namespace: $namespace,
            resourceNamespaceSuffix: 'Resource',
            requestNamespaceSuffix: 'Requests',
            dtoNamespaceSuffix: 'Dto',
            ignoredQueryParams: [
                'after',
                'order_by',
                'per_page',
            ],
            extra: [
                'excludePut' => $excludePut,
            ],
            configKey: $configKey,
            baseUrl: $baseUrl,
        );

        $generator = $this->createCodeGenerator($config);

        if ($generateFactories) {
            $generator->registerPostProcessor(new FactoryGenerator);
        }

        if ($generateFoundation) {
            $generator->registerPostProcessor(new ConfigPostProcessor);
            $generator->registerPostProcessor(new ServiceProviderPostProcessor);

            if ($generateTests) {
                $generator->registerPostProcessor(new TestSetupPostProcessor);
            }
        }

        if ($generateTests) {
            $generator->registerPostProcessor($this->createTestPostProcessor());
        }

        $result = $generator->run($specification);

        if ($this->option('dry-run')) {
            $this->printGeneratedFiles($result);

            return self::SUCCESS;
        }

        $this->cleanGeneratedDirectories($outputDir, $config, $generateFactories, $generateTests);

        $this->dumpGeneratedFiles($result);

        if ($generateFoundation) {
            $connectorClassName = $result->connectorClass
                ? Arr::first($result->connectorClass->getClasses())?->getName()
                : null;

            $composerSetup->setup($namespace, $this->getOutput(), false, $connectorClassName);
        }

        (new PintRunner)->run($outputDir, $this->getOutput());

        $this->info("SDK generated successfully in $outputDir");

        return self::SUCCESS;
    }

    /**
     * Override to customize the CodeGenerator, e.g. to inject custom
     * Request/Connector/Resource/Dto generators for a specific API.
     */
    protected function createCodeGenerator(Config $config): CodeGenerator
    {
        return new CodeGenerator(config: $config);
    }

    /**
     * Override to customize the Pest test PostProcessor, e.g. to change how
     * endpoints are routed to test generators for a specific API.
     */
    protected function createTestPostProcessor(): PostProcessor
    {
        return new PestTestGenerator;
    }

    protected function isUrl(string $path): bool
    {
        return str_starts_with($path, 'http://') || str_starts_with($path, 'https://');
    }

    protected function downloadSpec(string $url): string
    {
        $this->line("Downloading spec from $url...");

        $content = @file_get_contents($url);

        if ($content === false) {
            throw new RuntimeException("Failed to download specification from $url");
        }

        $extension = str_ends_with($url, '.json') ? '.json' : '.yaml';
        $tempFile = tempnam(sys_get_temp_dir(), 'openapi').$extension;
        file_put_contents($tempFile, $content);

        return $tempFile;
    }

    protected function excludePutRequests(ApiSpecification $specification): void
    {
        $specification->endpoints = array_values(array_filter(
            $specification->endpoints,
            fn (Endpoint $endpoint) => ! $endpoint->method->isPut(),
        ));
    }

    protected function printGeneratedFiles(GeneratedCode $result): void
    {
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

        $this->comment("\nDTOs:");
        foreach ($result->dtoClasses as $dtoClass) {
            $this->line(Utils::formatNamespaceAndClass($dtoClass));
        }

        if (! $this->option('skip-tests')) {
            $this->comment("\nTests:");
            foreach ($result->getWithTag('pest') as $test) {
                $this->line($test->path);
            }
        }

        if (! $this->option('skip-factories')) {
            $this->comment("\nFactories:");
            foreach ($result->getWithTag('factories') as $factory) {
                $this->line($factory->path);
            }
        }

        if ($this->option('foundation')) {
            $this->comment("\nFoundation:");
            foreach ($result->getWithTag('foundation') as $file) {
                $this->line($file->path);
            }
        }
    }

    protected function cleanGeneratedDirectories(string $outputDir, Config $config, bool $generateFactories, bool $generateTests): void
    {
        $directories = [
            "$outputDir/src/{$config->dtoNamespaceSuffix}",
            "$outputDir/src/{$config->requestNamespaceSuffix}",
            "$outputDir/src/{$config->resourceNamespaceSuffix}",
        ];

        if ($generateFactories) {
            $directories[] = "$outputDir/{$config->factoryNamespaceSuffix}";
        }

        if ($generateTests) {
            $directories[] = "$outputDir/tests/Requests";
        }

        foreach ($directories as $directory) {
            $this->deleteDirectory($directory);
        }
    }

    protected function deleteDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        foreach (scandir($directory) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = "$directory/$item";

            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }

        rmdir($directory);
    }

    protected function dumpGeneratedFiles(GeneratedCode $result): void
    {
        $this->title('Generated Files');

        $this->comment("\nConnector:");
        if ($result->connectorClass) {
            $this->dumpToFile($result->connectorClass);
        }

        $this->comment("\nResources:");
        foreach ($result->resourceClasses as $resourceClass) {
            $this->dumpToFile($resourceClass);
        }

        $this->comment("\nRequests:");
        foreach ($result->requestClasses as $requestClass) {
            $this->dumpToFile($requestClass);
        }

        $this->comment("\nDTOs:");
        foreach ($result->dtoClasses as $dtoClass) {
            $this->dumpToFile($dtoClass);
        }

        if (! $this->option('skip-tests')) {
            $this->comment("\nTests:");
            $this->dumpTaggedFiles($result->getWithTag('pest'));
        }

        $otherFiles = collect($result->additionalFiles)
            ->filter(fn ($file) => $file instanceof \Crescat\SaloonSdkGenerator\Data\TaggedOutputFile)
            ->filter(fn ($file) => $file->tag !== 'pest')
            ->values();

        if ($otherFiles->isNotEmpty()) {
            $this->comment("\nProject Files:");
            $this->dumpTaggedFiles($otherFiles);
        }
    }

    protected function dumpTaggedFiles(iterable $files): void
    {
        foreach ($files as $file) {
            $filePath = $this->option('output').'/'.$file->path;

            if (! file_exists(dirname($filePath))) {
                mkdir(dirname($filePath), recursive: true);
            }

            if (file_exists($filePath) && ! $this->option('force')) {
                $this->warn("- File already exists: $filePath");

                continue;
            }

            $ok = file_put_contents($filePath, $file->file);

            if ($ok === false) {
                $this->error("- Failed to write: $filePath");
            } else {
                $this->line("- Created: $filePath");
            }
        }
    }

    protected function dumpToFile(PhpFile $file): void
    {
        $wip = sprintf(
            '%s/src/%s/%s.php',
            $this->option('output'),
            str_replace($this->namespace, '', Arr::first($file->getNamespaces())->getName()),
            Arr::first($file->getClasses())?->getName(),
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
}
