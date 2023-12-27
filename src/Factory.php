<?php

declare(strict_types=1);

namespace Crescat\SaloonSdkGenerator;

use Crescat\SaloonSdkGenerator\Contracts\Parser;
use Crescat\SaloonSdkGenerator\Data\Generator\ApiSpecification;
use Crescat\SaloonSdkGenerator\Exceptions\ParserNotRegisteredException;
use Crescat\SaloonSdkGenerator\Parsers\OpenApiParser;
use Crescat\SaloonSdkGenerator\Parsers\PostmanCollectionParser;

class Factory
{
    /**
     * @var array<string, Parser>
     */
    protected static array $registeredParsers = [
        'openapi' => OpenApiParser::class,
        'postman' => PostmanCollectionParser::class,
    ];

    public static function getRegisteredParsers(): array
    {
        return self::$registeredParsers;
    }

    public static function getRegisteredParserTypes(): array
    {
        return array_keys(self::$registeredParsers);
    }

    public static function registerParser(string $name, string $className): void
    {
        self::$registeredParsers[$name] = $className;
    }

    /**
     * @throws ParserNotRegisteredException
     */
    public static function createParser(string $type, mixed $input): ?Parser
    {
        if (isset(self::$registeredParsers[$type])) {
            $className = self::$registeredParsers[$type];

            // If required, call the "build" method that can accept anything and return an instance of the parser.
            if (method_exists($className, 'build')) {
                return call_user_func([$className, 'build'], $input);
            }

            return new $className($input);
        }

        throw new ParserNotRegisteredException("No parser registered for '$type'");
    }

    /**
     * @throws ParserNotRegisteredException
     */
    public static function parse(string $type, mixed $input): ApiSpecification
    {
        return self::createParser($type, $input)->parse();
    }
}
