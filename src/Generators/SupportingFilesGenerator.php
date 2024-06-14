<?php

declare(strict_types=1);

namespace Crescat\SaloonSdkGenerator\Generators;

use Composer\InstalledVersions;
use Crescat\SaloonSdkGenerator\Data\Generator\ApiSpecification;
use Crescat\SaloonSdkGenerator\Data\Generator\Config;
use Crescat\SaloonSdkGenerator\Enums\SupportingFile;
use Crescat\SaloonSdkGenerator\Generator;
use Exception;
use Nette\PhpGenerator\PhpFile;

class SupportingFilesGenerator extends Generator
{
    /**
     * @var array<string, string[]>
     */
    public static array $included = [
        SupportingFile::CONTRACT->value => ['Deserializable'],
        SupportingFile::EXCEPTION->value => ['InvalidAttributeTypeException'],
        SupportingFile::TRAIT->value => ['Deserializes', 'HasArrayableAttributes', 'HasComplexArrayTypes'],
    ];

    public function generate(ApiSpecification $specification): PhpFile|array
    {
        $files = [];
        foreach (static::$included as $type => $paths) {
            $_type = SupportingFile::from($type);
            $namespace = $this->config->getSupportingFilesNamespace($_type);
            $paths = $this->getFiles($_type, $paths);
            $files[$type] = $this->generateFiles($namespace, $paths);
        }

        return $files;
    }

    /**
     * @param  string[]  $paths
     * @return PhpFile[]
     */
    protected function generateFiles(string $namespace, array $paths): array
    {
        $composer = json_decode(file_get_contents($this->getInstallPath().'/composer.json'), true);
        $originalNamespace = array_keys($composer['autoload']['psr-4'])[0];
        $files = [];
        foreach ($paths as $filePath) {
            // The Nette\PhpGenerator doesn't allow us to change namespaces or use statements, so we have to do it manually
            $content = str_replace(
                $originalNamespace,
                "{$this->config->getSupportingFilesNamespace()}\\",
                file_get_contents($filePath),
            );

            if ($this->config->datetimeFormat !== Config::DEFAULT_OPTIONS['datetimeFormat']) {
                $content = str_replace(
                    Config::DEFAULT_OPTIONS['datetimeFormat'],
                    $this->config->datetimeFormat,
                    $content
                );
            }

            if ($content === false) {
                throw new Exception("Failed to read file: {$filePath}");
            }

            $file = PhpFile::fromCode($content);
            $file->setStrictTypes();
            $file->addNamespace($namespace);

            $files[] = $file;
        }

        return $files;
    }

    protected function getFiles(SupportingFile $type, array $classes): array
    {
        $files = glob("{$this->getInstallPath()}/src/{$type->value}/*.php");

        return array_filter(
            $files,
            fn ($filePath) => in_array(basename($filePath, '.php'), $classes)
        );
    }

    protected function getInstallPath(): string
    {
        $packageName = 'highsidelabs/saloon-sdk-generator';

        return InstalledVersions::getInstallPath($packageName);
    }
}
