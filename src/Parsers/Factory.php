<?php

namespace Crescat\SaloonSdkGenerator\Parsers;

use Crescat\SaloonSdkGenerator\Contracts\Parser;
use Crescat\SaloonSdkGenerator\Data\Generator\Endpoints;

class Factory
{
    protected static array $registeredParsers = [
        'openapi' => OpenApiParser::class,
        'postman' => PostmanCollectionParser::class,
    ];

    public static function registerParser(string $name, string $className): void
    {
        self::$registeredParsers[$name] = $className;
    }

    public static function createParser(string $type, $input): ?Parser
    {
        if (isset(self::$registeredParsers[$type])) {
            $className = self::$registeredParsers[$type];

            // If required, call the "build" method that can accept anything and return an instance of the parser.
            if (method_exists($className, 'build')) {
                return call_user_func([$className, 'build'], $input);
            }

            return new $className($input);
        }

        return null;
    }

    public static function parse($type, $input): Endpoints
    {
        return self::createParser($type, $input)->parse();
    }
}
