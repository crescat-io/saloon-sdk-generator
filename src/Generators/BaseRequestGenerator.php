<?php

declare(strict_types=1);

namespace Crescat\SaloonSdkGenerator\Generators;

use Crescat\SaloonSdkGenerator\Data\Generator\ApiSpecification;
use Crescat\SaloonSdkGenerator\Generator;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpFile;
use Saloon\Http\Request;

class BaseRequestGenerator extends Generator
{
    public static string $baseClsName = 'Request';

    public function generate(ApiSpecification $specification): PhpFile|array
    {
        $classType = new ClassType(static::$baseClsName);
        $classFile = new PhpFile();
        $namespace = $this->config->baseFilesNamespace();

        $classType->setExtends(Request::class)
            ->setAbstract();
        $classFile->setStrictTypes()
            ->addNamespace($namespace)
            ->add($classType)
            ->addUse(Request::class, 'SaloonRequest');

        return $classFile;
    }
}
