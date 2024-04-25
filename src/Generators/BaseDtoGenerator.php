<?php

declare(strict_types=1);

namespace Crescat\SaloonSdkGenerator\Generators;

use Crescat\SaloonSdkGenerator\Data\Generator\ApiSpecification;
use Crescat\SaloonSdkGenerator\Enums\SupportingFile;
use Crescat\SaloonSdkGenerator\Generator;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpFile;

class BaseDtoGenerator extends Generator
{
    public static string $baseClsName = 'Dto';

    public function generate(ApiSpecification $specification): PhpFile|array
    {
        $classType = new ClassType(static::$baseClsName);
        $classFile = new PhpFile();
        $namespace = $this->config->baseFilesNamespace();
        $contractNamespace = $this->config->getSupportingFilesNamespace(SupportingFile::CONTRACT);
        $traitNamespace = $this->config->getSupportingFilesNamespace(SupportingFile::TRAIT);

        $classFile->setStrictTypes()
            ->addNamespace($namespace)
            ->addUse($contractNamespace.'\\Deserializable')
            ->addUse($traitNamespace.'\\Deserializes')
            ->addUse($traitNamespace.'\\HasArrayableAttributes')
            ->add($classType);

        $classType->addImplement($contractNamespace.'\\Deserializable');
        $classType->addTrait($traitNamespace.'\\Deserializes');
        $classType->addTrait($traitNamespace.'\\HasArrayableAttributes');

        return $classFile;
    }
}
