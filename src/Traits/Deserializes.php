<?php

declare(strict_types=1);

namespace Crescat\SaloonSdkGenerator\Traits;

use Crescat\SaloonSdkGenerator\Enums\SimpleType;
use Crescat\SaloonSdkGenerator\Exceptions\InvalidAttributeTypeException;
use DateTime;
use ReflectionClass;

trait Deserializes
{
    use HasComplexArrayTypes;

    public function __deserialize(array $data): static
    {
        return static::deserialize($data);
    }

    public static function deserialize(mixed $data): mixed
    {
        if (is_null($data)) {
            return null;
        }

        $reflectionClass = new ReflectionClass(static::class);
        $constructor = $reflectionClass->getConstructor();
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

        $hasAttributeMap = $reflectionClass->hasProperty('attributeMap');
        $attributeMap = $hasAttributeMap
            ? array_flip($reflectionClass->getProperty('attributeMap')->getValue())
            : [];
        $unknownKeys = [];

        foreach ($data as $rawKey => $value) {
            $key = $rawKey;
            if ($hasAttributeMap) {
                $key = $attributeMap[$rawKey] ?? $key;
            }

            if (! array_key_exists($key, $attributeTypes)) {
                $unknownKeys[] = $key;

                continue;
            }
            $deserializedParams[$key] = static::deserializeValue($value, $attributeTypes[$key]);
        }

        if (count($unknownKeys) > 0) {
            $cls = static::class;
            echo "Warning: Unknown keys when deserializing into $cls: ".implode(', ', $unknownKeys)."\n";
        }

        return new static(...$deserializedParams);
    }

    protected static function deserializeValue(mixed $value, SimpleType|array|string $type): mixed
    {
        if (is_string($type) && ($simpleType = SimpleType::tryFrom($type))) {
            return match ($simpleType) {
                SimpleType::INTEGER => (int) $value,
                SimpleType::FLOAT => (float) $value,
                SimpleType::BOOLEAN => (bool) $value,
                SimpleType::STRING => (string) $value,
                SimpleType::DATE, SimpleType::DATETIME => DateTime::createFromFormat(DateTime::RFC3339, $value),
                SimpleType::ARRAY, SimpleType::MIXED => $value,
                SimpleType::NULL => null,
            };
        } elseif (is_string($type)) {
            if (! class_exists($type)) {
                throw new InvalidAttributeTypeException("Class `$type` does not exist");
            } elseif ($type === DateTime::class) {
                return DateTime::createFromFormat(DateTime::RFC3339, $value);
            }

            $deserialized = $type::deserialize($value);

            return $type::deserialize($value);
        } elseif (is_array($type)) {
            $typeLen = count($type);
            if ($typeLen !== 1) {
                throw new InvalidAttributeTypeException(
                    "Complex array type must have a single value (the type of the array items), $typeLen given"
                );
            }

            $deserialized = [];
            foreach ($value as $item) {
                $deserialized[] = static::deserializeValue($item, $type[0]);
            }

            return $deserialized;
        }

        throw new InvalidAttributeTypeException("Invalid type `$type`");
    }
}
