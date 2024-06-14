<?php

declare(strict_types=1);

namespace Crescat\SaloonSdkGenerator\Helpers;

namespace Crescat\SaloonSdkGenerator\Helpers;

use Crescat\SaloonSdkGenerator\Data\Generator\Parameter;
use InvalidArgumentException;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Dumper;
use Nette\PhpGenerator\Literal;
use Nette\PhpGenerator\Method;
use Nette\PhpGenerator\Parameter as PhpGeneratorParameter;

class MethodGeneratorHelper
{
    /**
     * Adds a property to a method based on a given parameter.
     *
     * @param  Method  $method  The method to which the parameter is added.
     * @param  Parameter  $parameter  The parameter to add.
     * @param  bool  $promote  Whether the parameter should be promoted to a property.
     * @param  string  $visibility  The visibility of the property. Only relevant if $promote = true.
     * @param  bool  $readonly  Whether the property is read-only. Only relevant if $promote = true.
     * @param  ?string  $namespace  The namespace of the parameter's type (if any).
     * @return PhpGeneratorParameter The added parameter.
     */
    public static function addParameterToMethod(
        Method $method,
        Parameter $parameter,
        bool $promote = false,
        string $visibility = 'protected',
        bool $readonly = false,
        ?string $namespace = null,
    ): PhpGeneratorParameter {
        $name = NameHelper::safeVariableName($parameter->name);

        $method->addComment(
            trim(sprintf(
                '@param %s $%s %s',
                // TODO: if the type is aliased, we should detect it and note it in the
                // schema object somehow so that accurate docstrings are generated
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
        if ($type === 'DateTime') {
            $type = '\DateTimeInterface';
        } elseif (! Utils::isBuiltInType($type)) {
            if ($namespace === null) {
                throw new InvalidArgumentException('$namespace must be passed if the type is not a built-in.');
            }
            $safeType = NameHelper::safeClassName($type);
            $type = "{$namespace}\\{$safeType}";
        }

        $property
            ->setType($type)
            ->setNullable($parameter->isNullable());

        if ($parameter->isNullable()) {
            $property->setDefaultValue(null);
        }

        return $property;
    }

    /**
     * Generates a method that returns parameters as an array.
     */
    public static function generateArrayReturnMethod(ClassType $classType, string $name, array $parameters, string $datetimeFormat, bool $withArrayFilterWrapper = false): Method
    {
        $paramArray = self::buildParameterArray($parameters, $datetimeFormat);

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
    protected static function buildParameterArray(array $parameters, string $datetimeFormat): array
    {
        return collect($parameters)
            ->mapWithKeys(function (Parameter $parameter) use ($datetimeFormat) {
                $safeName = NameHelper::safeVariableName($parameter->name);

                if (Utils::isBuiltInType($parameter->type)) {
                    if ($parameter->type === 'DateTime') {
                        $printStr = '$this->%s?->format(\''.$datetimeFormat.'\')';
                    } else {
                        $printStr = '$this->%s';
                    }
                    $paramCode = new Literal(sprintf($printStr, $safeName));
                } else {
                    $paramCode = new Literal(sprintf('array_filter($this->%s->toArray())', $safeName));
                }

                return [$parameter->rawName => $paramCode];
            })
            ->toArray();
    }
}
