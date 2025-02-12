<?php

declare(strict_types=1);

namespace Crescat\SaloonSdkGenerator\Generators;

use Crescat\SaloonSdkGenerator\Data\Generator\ApiSpecification;
use Crescat\SaloonSdkGenerator\Enums\SupportingFile;
use Crescat\SaloonSdkGenerator\Generator;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpFile;
use Saloon\Contracts\DataObjects\WithResponse;
use Saloon\Traits\Responses\HasResponse;

class BaseResponseGenerator extends Generator
{
    public static string $baseClsName = 'Response';

    public function generate(ApiSpecification $specification): PhpFile|array
    {
        $baseClassType = new ClassType(static::$baseClsName);
        $baseClassFile = new PhpFile();
        $namespace = $this->config->baseFilesNamespace();
        $traitNamespace = $this->config->getSupportingFilesNamespace(SupportingFile::TRAIT);
        $contractNamespace = $this->config->getSupportingFilesNamespace(SupportingFile::CONTRACT);

        $baseClassFqn = $this->baseClassFqn();
        $baseClassFile->setStrictTypes()
            ->addNamespace($namespace)
            ->addUse(HasResponse::class)
            ->addUse(WithResponse::class)
            ->addUse($contractNamespace.'\\Deserializable')
            ->addUse($traitNamespace.'\\Deserializes')
            ->add($baseClassType);

        $baseClassType->addImplement($contractNamespace.'\\Deserializable')
            ->addImplement(WithResponse::class)
            ->setAbstract();

        $baseClassType->addTrait($traitNamespace.'\\Deserializes');
        $baseClassType->addTrait(HasResponse::class);

        // Create an empty response class that extends the base response class
        $emptyClassType = new ClassType('EmptyResponse');
        $emptyClassFile = new PhpFile();
        $emptyClassFile->setStrictTypes()
            ->addNamespace($namespace)
            ->add($emptyClassType)
            ->addUse($baseClassFqn);

        $emptyClassType->addMethod('__construct');
        $emptyClassType->setExtends($baseClassFqn);

        return [$baseClassFile, $emptyClassFile];
    }
}
