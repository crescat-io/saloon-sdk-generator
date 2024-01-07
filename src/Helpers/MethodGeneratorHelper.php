<?php

declare(strict_types=1);

namespace Crescat\SaloonSdkGenerator\Helpers;

namespace Crescat\SaloonSdkGenerator\Helpers;

use Crescat\SaloonSdkGenerator\Data\Generator\Parameter;
use Crescat\SaloonSdkGenerator\Enums\SimpleType;
use InvalidArgumentException;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Dumper;
use Nette\PhpGenerator\Literal;
use Nette\PhpGenerator\Method;

class MethodGeneratorHelper
{
    /**
     * Adds a property to a constructor based on a given parameter.
     *
     * @param  Method  $constructor The consetructor to which the promoted property is added.
     * @param  Parameter  $parameter The parameter based on which the promoted property is added.
     * @param  bool  $promote  Whether the parameter should be promoted to a property.
     * @param  string  $visibility The visibility of the promoted property.
     * @param  bool  $readonly Whether the promoted property is read-only.
     * @param  ?string  $namespace  The namespace of the promoted property's type (if any).
     * @return Method The updated method with the promoted property.
     */
    public static function addParameterToConstructor(
        Method $method,
        Parameter $parameter,
        bool $promote = true,
        string $visibility = 'protected',
        bool $readonly = false,
        ?string $namespace = null,
    ): Method {
        $name = NameHelper::safeVariableName($parameter->name);

        $method->addComment(
            trim(sprintf(
                '@param %s $%s %s',
                $parameter->getDocTypeString(),
                $name,
                $parameter->description
            ))
        );

        if ($promote) {
            $property = $method->addPromotedParameter($name);
            $property
                ->setReadOnly($readonly)
                ->setVisibility($visibility);
        } else {
            $property = $method->addParameter($name);
        }

        $type = $parameter->type;
        if (! SimpleType::tryFrom($type)) {
            if (! $namespace) {
                throw new InvalidArgumentException('$namespace must be passed if the type is not a built-in.');
            }
            $type = "{$namespace}\\{$type}";
        }

        $property
            ->setType($type)
            ->setNullable($parameter->isNullable());

        if ($parameter->isNullable()) {
            $property->setDefaultValue(null);
        }

        return $method;
    }

    /**
     * Generates a method that returns parameters as an array.
     */
    public static function generateArrayReturnMethod(ClassType $classType, string $name, array $parameters, bool $withArrayFilterWrapper = false): Method
    {
        $paramArray = self::buildParameterArray($parameters);

        $body = $withArrayFilterWrapper
            ? sprintf('return array_filter(%s);', (new Dumper)->dump($paramArray))
            : sprintf('return %s;', (new Dumper)->dump($paramArray));

        return $classType
            ->addMethod($name)
            ->setReturnType('array')
            ->addBody($body);
    }

    /**
     * Builds an array of parameters with their corresponding values.
     */
    protected static function buildParameterArray(array $parameters): array
    {
        return collect($parameters)
            ->mapWithKeys(function (Parameter $parameter) {
                $name = $parameter->name;
                $safeName = NameHelper::safeVariableName($name);

                if (SimpleType::tryFrom($parameter->type)) {
                    $paramCode = new Literal(sprintf('$this->%s', $safeName));
                } else {
                    $paramCode = new Literal(sprintf('array_filter($this->%s->toArray())', $safeName));
                }

                return [$safeName => $paramCode];
            })
            ->toArray();
    }
}
