<?php

namespace Crescat\SaloonSdkGenerator\Generators;

use cebe\openapi\spec\Type;
use Crescat\SaloonSdkGenerator\Contracts\Deserializable;
use Crescat\SaloonSdkGenerator\Data\Generator\ApiSpecification;
use Crescat\SaloonSdkGenerator\Data\Generator\Schema;
use Crescat\SaloonSdkGenerator\Generator;
use Crescat\SaloonSdkGenerator\Helpers\NameHelper;
use Crescat\SaloonSdkGenerator\Traits\Deserializes;
use Nette\PhpGenerator\PhpFile;
use Saloon\Contracts\DataObjects\WithResponse;
use Saloon\Traits\Responses\HasResponse;

class ResponseGenerator extends Generator
{
    public function generate(ApiSpecification $specification): PhpFile|array
    {
        $classes = [];

        foreach ($specification->responses as $response) {
            $classes[] = $this->generateResponseClass($response);
        }

        return $classes;
    }

    public function generateResponseClass(Schema $schema): PhpFile
    {
        $className = NameHelper::responseClassName($schema->name);
        [$classFile, $namespace, $classType] = $this->makeClass($className, $this->config->responseNamespaceSuffix);

        $namespace
            ->addUse(Deserializes::class)
            ->addUse(WithResponse::class)
            ->addUse(Deserializable::class)
            ->addUse(HasResponse::class);

        $classType
            ->setFinal()
            ->addImplement(Deserializable::class)
            ->addImplement(WithResponse::class)
            ->addTrait(HasResponse::class);

        // Can't chain addTrait calls
        $classType->addTrait(Deserializes::class);

        $classConstructor = $classType->addMethod('__construct');

        foreach ($schema->properties as $property) {
            $name = NameHelper::safeVariableName($property->name);
            $param = $classConstructor
                ->addComment(
                    trim(sprintf(
                        '@param %s $%s %s',
                        $property->getDocTypeString(),
                        $name,
                        $property->description
                    ))
                )
                ->addPromotedParameter($name);

            $type = $property->type;
            if (! Type::isScalar($type)) {
                $type = "{$namespace->getName()}\\{$type}";
            }
            $param
                ->setReadOnly()
                ->setType($type)
                ->setNullable($property->nullable)
                ->setPublic();

            if ($property->nullable) {
                $param->setDefaultValue(null);
            }
        }

        return $classFile;
    }
}
