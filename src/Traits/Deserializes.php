<?php

declare(strict_types=1);

namespace Crescat\SaloonSdkGenerator\Traits;

use Crescat\SaloonSdkGenerator\Enums\SimpleType;
use Crescat\SaloonSdkGenerator\Exceptions\InvalidAttributeTypeException;
use DateTime;
use ReflectionClass;

trait Deserializes
{
    /**
     * Override this to specify the item types (and key types, if necessary) of attributes.
     * Valid values either look like ['attributeName' => [SomeType::class]]
     * or ['attributeName' => [SimpleType::STRING|SimpleType::INTEGER, OtherType::class]].
     */
    protected static array $complexArrayTypes = [];

    public function __deserialize(array $data): static
    {
        return static::deserialize($data);
    }

    public static function deserialize(mixed $data): mixed
    {
        if (is_null($data)) {
            return null;
        }

        $constructor = (new ReflectionClass(static::class))->getConstructor();
        if (! $constructor) {
            throw new InvalidAttributeTypeException('Class to be deserialized must have a constructor');
        }
        $reflectionParams = $constructor->getParameters() ?? [];

        $attributeTypes = [];
        $deserializedParams = [];
        foreach ($reflectionParams as $param) {
            $name = $param->getName();
            $type = $param->getType()->getName();

            // `array` could either be read as a simple PHP array, or a typed array that
            // we want to deserialize into an array of objects
            if ($type === 'array') {
                $type = static::getArrayType($name);
            }

            $attributeTypes[$name] = $type;
        }

        $unknownKeys = [];
        foreach ($data as $key => $value) {
            if (! array_key_exists($key, $attributeTypes)) {
                $unknownKeys[] = $key;

                continue;
            }
            $deserializedParams[$key] = static::deserializeValue($value, $attributeTypes[$key]);
        }

        if (count($unknownKeys) > 0) {
            $cls = static::class;
            echo "[WARNING] Unknown keys when deserializing into $cls: ".implode(', ', $unknownKeys)."\n";
        }

        return new static(...$deserializedParams);
    }

    protected static function deserializeValue(mixed $value, SimpleType|array|string $type): mixed
    {
        if (is_string($type) && ($simpleType = SimpleType::tryFrom($type))) {
            return match ($simpleType) {
                SimpleType::INTEGER => (int) $value,
                SimpleType::NUMBER => (float) $value,
                SimpleType::BOOLEAN => (bool) $value,
                SimpleType::STRING => (string) $value,
                SimpleType::DATE, SimpleType::DATETIME => new DateTime($value),
                SimpleType::ARRAY => $value,
                SimpleType::NULL => null,
            };
        } elseif (is_string($type)) {
            if (! class_exists($type)) {
                throw new InvalidAttributeTypeException("Class `$type` does not exist");
            }

            $deserialized = $type::deserialize($value);

            return $type::deserialize($value);
        } elseif (is_array($type)) {
            $deserialized = [];
            $typeLen = count($type);
            if ($typeLen === 1) {
                foreach ($value as $item) {
                    $deserialized[] = static::deserializeValue($item, $type[0]);
                }
            } elseif ($typeLen === 2) {
                $deserialized = [];

                $keyType = $type[0];
                if ($keyType !== SimpleType::STRING && $keyType !== SimpleType::INTEGER) {
                    throw new InvalidAttributeTypeException("Array key must be a string or an integer, `$keyType` given");
                }

                foreach ($value as $k => $v) {
                    $deserializedKey = static::deserializeValue($k, $keyType);
                    $deserializedValue = static::deserialize($v, $type[1]);
                    $deserialized[$deserializedKey] = $deserializedValue;
                }
            } else {
                throw new InvalidAttributeTypeException(
                    "Complex array type must have one value (the type of the array items) or two values (the key and the type of the array items), $typeLen given"
                );
            }

            return $deserialized;
        }

        throw new InvalidAttributeTypeException("Invalid type `$type`");
    }

    protected static function getArrayType(string $attributeName): array|string
    {
        if (! array_key_exists($attributeName, static::$complexArrayTypes)) {
            return 'array';
        }

        return static::$complexArrayTypes[$attributeName];
    }
}
