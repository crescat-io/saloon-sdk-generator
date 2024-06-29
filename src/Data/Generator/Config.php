<?php

declare(strict_types=1);

namespace Crescat\SaloonSdkGenerator\Data\Generator;

use Composer\Autoload\ClassLoader;
use Crescat\SaloonSdkGenerator\Enums\SupportingFile;
use Crescat\SaloonSdkGenerator\Helpers\NameHelper;
use DateTime;
use Exception;
use Illuminate\Support\Arr;
use ReflectionClass;

class Config
{
    const CONFIG_OPTS = [
        'connectorName', 'namespace', 'namespaceSuffixes', 'baseFilesNamespace', 'fallbackResourceName',
        'type', 'outputDir', 'force',
        'ignoredParams', 'datetimeFormat', 'extra',
    ];

    const REQUIRED_OPTS = ['connectorName', 'namespace'];

    const DEFAULT_OPTIONS = [
        'baseFilesNamespace' => '',
        'namespaceSuffixes' => [
            'resource' => 'Resource',
            'request' => 'Requests',
            'response' => 'Responses',
            'dto' => 'Dto',
        ],
        'fallbackResourceName' => 'Misc',
        'type' => 'postman',
        'outputDir' => './build',
        'force' => false,
        'ignoredParams' => [
            'query' => [],
            'body' => [],
            'header' => [],
        ],
        'datetimeFormat' => DateTime::RFC3339,
        'extra' => [],
    ];

    public readonly array $namespaceSuffixes;

    public readonly string $baseFilesNamespace;

    /** @var array{query: array<string>, body: array<string>, header: array<string>} */
    public readonly array $ignoredParams;

    /**
     * @param  string|null  $connectorName  The name of the connector class.
     * @param  string|null  $namespace  The main namespace for the generated SDK.
     * @param  string|null  $baseFilesNamespace  The namespace for the supporting files.
     * @param  array|null  $namespaceSuffixes  The options for the namespace.
     * @param  string|null  $fallbackResourceName  The default name to use for resources if none could be inferred from the specification.
     * @param  string|null  $type  The type of API specification to parse.
     * @param  string|null  $outputDir  The output directory where the generated code will be saved.
     * @param  bool|null  $force  Whether to overwrite existing files.
     * @param  array{query: array<string>, body: array<string>, header: array<string>}  $ignoredParams
     *                                                                                                  Associative list of query, body and header parameters that should be ignored.
     * @param  string  $datetimeFormat  The format to use for date and datetime parameters.
     * @param  array  $extra  Additional configuration for custom code generators.
     */
    public function __construct(
        public readonly ?string $connectorName,
        public readonly ?string $namespace,
        ?string $baseFilesNamespace = '',
        ?array $namespaceSuffixes = self::DEFAULT_OPTIONS['namespaceSuffixes'],
        public readonly ?string $fallbackResourceName = self::DEFAULT_OPTIONS['fallbackResourceName'],

        public readonly ?string $type = self::DEFAULT_OPTIONS['type'],
        public readonly ?string $outputDir = self::DEFAULT_OPTIONS['outputDir'],
        public readonly ?bool $force = self::DEFAULT_OPTIONS['force'],

        array $ignoredParams = self::DEFAULT_OPTIONS['ignoredParams'],
        public readonly string $datetimeFormat = self::DEFAULT_OPTIONS['datetimeFormat'],
        public readonly array $extra = self::DEFAULT_OPTIONS['extra'],
    ) {
        $this->namespaceSuffixes = array_merge(
            self::DEFAULT_OPTIONS['namespaceSuffixes'],
            $namespaceSuffixes
        );
        if (! $baseFilesNamespace) {
            $this->baseFilesNamespace = $this->namespace;
        } else {
            $this->baseFilesNamespace = $baseFilesNamespace;
        }
        $this->ignoredParams = array_merge(self::DEFAULT_OPTIONS['ignoredParams'], $ignoredParams);
    }

    public function baseFilesNamespace(): string
    {
        return $this->baseFilesNamespace
            ? $this->baseFilesNamespace
            : $this->namespace;
    }

    public function resourceNamespace(): string
    {
        return $this->namespaceWithSuffix('resource');
    }

    public function requestNamespace(): string
    {
        return $this->namespaceWithSuffix('request');
    }

    public function responseNamespace(): string
    {
        return $this->namespaceWithSuffix('response');
    }

    public function dtoNamespace(): string
    {
        return $this->namespaceWithSuffix('dto');
    }

    public function getSupportingFilesNamespace(?SupportingFile $type = null): string
    {
        $typeNs = $type ? "\\{$type->value}" : '';

        return $this->baseFilesNamespace().$typeNs;
    }

    protected function namespaceWithSuffix(string $type): string
    {
        $suffix = NameHelper::optionalNamespaceSuffix($this->namespaceSuffixes[$type]);

        return "{$this->namespace}{$suffix}";
    }

    /**
     * Load configuration from a JSON file. If no path is provided, it will look for
     * a file named generator-config.json in the root project directory.
     *
     * @throws Exception
     */
    public static function load(?string $path = null, array $overrides = []): static
    {
        // Find the root project directory
        $reflection = new ReflectionClass(ClassLoader::class);
        $vendorDir = dirname($reflection->getFileName(), 3);
        $path ??= $vendorDir.'/generator-config.json';

        $file = file_get_contents($path);
        if ($file === false) {
            throw new Exception("Failed to open config file: $path");
        }
        try {
            $config = json_decode($file, true);
        } catch (Exception $e) {
            throw new Exception("Failed to parse config file: $path");
        }

        $missingKeys = array_diff(self::REQUIRED_OPTS, array_keys($config));
        $unknownKeys = array_diff(array_keys($config), self::CONFIG_OPTS);
        if (! empty($missingKeys)) {
            throw new Exception('Missing required config file keys: '.implode(', ', $missingKeys));
        }
        if (! empty($unknownKeys)) {
            echo '[WARNING] Unknown config file keys: '.implode(', ', $unknownKeys)."\n";
        }

        $getOpt = function (string $key, mixed $default = null) use ($overrides, $config) {
            $_default = $default ?? Arr::get(self::DEFAULT_OPTIONS, $key);

            return isset($overrides[$key])
                ? $overrides[$key]
                : Arr::get($config, $key, $_default);
        };

        $outputDir = $getOpt('outputDir', './build');

        return new static(
            connectorName: $overrides['connectorName'] ?? $config['connectorName'],
            namespace: $overrides['namespace'] ?? $config['namespace'],
            baseFilesNamespace: $getOpt('baseFilesNamespace'),
            namespaceSuffixes: [
                'resource' => $getOpt('namespaceSuffixes.resource'),
                'request' => $getOpt('namespaceSuffixes.request'),
                'response' => $getOpt('namespaceSuffixes.response'),
                'dto' => $getOpt('namespaceSuffixes.dto'),
            ],
            fallbackResourceName: $getOpt('fallbackResourceName'),

            type: $getOpt('type'),
            outputDir: trim($outputDir, '/'),
            force: $getOpt('force'),

            ignoredParams: $getOpt('ignoredParams'),
            datetimeFormat: $getOpt('datetimeFormat'),
            extra: $getOpt('extra'),
        );
    }
}
