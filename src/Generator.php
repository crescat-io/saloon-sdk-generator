<?php

namespace Crescat\SaloonSdkGenerator;

use Crescat\SaloonSdkGenerator\Contracts\Generator as GeneratorContract;
use Crescat\SaloonSdkGenerator\Data\Generator\Config;
use Crescat\SaloonSdkGenerator\Helpers\NameHelper;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PhpNamespace;

abstract class Generator implements GeneratorContract
{
    public function __construct(protected Config $config)
    {

    }

    /**
     * @return array{PhpFile, PhpNamespace, ClassType}
     */
    protected function makeClass(string $className, array|string $namespaceSuffixes = []): array
    {
        if (is_string($namespaceSuffixes)) {
            $namespaceSuffixes = [$namespaceSuffixes];
        }
        $classType = new ClassType($className);

        $classFile = new PhpFile;
        $suffixes = [];
        foreach ($namespaceSuffixes as $suffix) {
            $suffixes[] = NameHelper::optionalNamespaceSuffix($suffix);
        }
        $suffix = implode('', $suffixes);
        $namespace = $classFile->addNamespace("{$this->config->namespace}{$suffix}");
        $namespace->add($classType);

        return [$classFile, $namespace, $classType];
    }
}
