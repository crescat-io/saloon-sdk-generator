<?php

namespace Crescat\SaloonSdkGenerator\Generators;

use cebe\openapi\spec\Type;
use Crescat\SaloonSdkGenerator\Data\Generator\ApiSpecification;
use Crescat\SaloonSdkGenerator\Data\Generator\Schema;
use Crescat\SaloonSdkGenerator\Generator;
use Crescat\SaloonSdkGenerator\Helpers\NameHelper;
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

        $classType
            ->setFinal()
            ->setReadOnly()
            ->addImplement(WithResponse::class)
            ->addTrait(HasResponse::class);

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
