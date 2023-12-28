<?php

declare(strict_types=1);

namespace Crescat\SaloonSdkGenerator\Helpers;

use Illuminate\Support\Str;

class NameHelper
{
    public static array $reservedKeywords = [
        'void',
        'abstract',
        'and',
        'array',
        'as',
        'break',
        'callable',
        'case',
        'catch',
        'class',
        'clone',
        'const',
        'continue',
        'declare',
        'default',
        'die',
        'do',
        'echo',
        'else',
        'elseif',
        'empty',
        'enddeclare',
        'endfor',
        'endforeach',
        'endif',
        'endswitch',
        'endwhile',
        'eval',
        'exit',
        'extends',
        'false',
        'final',
        'finally',
        'fn',
        'for',
        'foreach',
        'function',
        'global',
        'goto',
        'if',
        'implements',
        'include',
        'include_once',
        'instanceof',
        'insteadof',
        'interface',
        'isset',
        'list',
        'match',
        'namespace',
        'new',
        'null',
        'or',
        'print',
        'private',
        'protected',
        'public',
        'readonly',
        'require',
        'require_once',
        'return',
        'static',
        'switch',
        'throw',
        'trait',
        'true',
        'try',
        'unset',
        'use',
        'var',
        'while',
        'xor',
        'yield',
    ];

    protected static array $variableNameCache = [];

    protected static array $classNameCache = [];

    public static function normalize(string $value): string
    {
        // TODO: Remove diacritics and umlauts etc
        //  See: https://stackoverflow.com/questions/3635511/remove-diacritics-from-a-string

        return Str::of($value)
            // Split by camelcase, ex: YearEndNote -> Year End Note
            ->replaceMatches('/([a-z])([A-Z])/', '$1 $2')
            ->replace(' a ', ' ')
            ->replace(' an ', ' ')
            ->replace("'s ", ' ')
            ->replace(':', ' ')
            ->replace('.', ' ')
            ->replace(',', ' ')
            ->replace('(', ' ')
            ->replace(')', ' ')
            ->replace('/', ' ')
            ->replace('-', ' ')
            ->replace('_', ' ')
            ->slug(' ')
            ->squish()
            ->trim()
            ->toString();
    }

    public static function preventNameCollisions(string $value, string $suffix = 'Class'): string
    {
        if (in_array(strtolower($value), self::$reservedKeywords)) {
            $value .= $suffix;
        }

        return $value;
    }

    /**
     * Transforms a string into something that can be used
     * as a method or variable identifier in PHP
     * ex: "list all (_new_) users" -> listAllNewUsers
     */
    public static function safeVariableName(string $value): string
    {
        if (isset(self::$variableNameCache[$value])) {
            return self::$variableNameCache[$value];
        }

        $result = Str::camel(self::normalize($value));
        self::$variableNameCache[$value] = $result;

        return $result;
    }

    /**
     * Transforms a string into something that can be used
     * as a method or variable identifier in PHP
     * ex: "list all (_new_) users" -> ListAllNewUsers
     */
    public static function safeClassName(string $value, string $collisionSuffix = 'Class'): string
    {
        if (isset(self::$classNameCache[$value])) {
            return self::$classNameCache[$value];
        }

        $result = Str::studly(self::preventNameCollisions(self::normalize($value), $collisionSuffix));
        self::$classNameCache[$value] = $result;

        return $result;
    }

    /**
     * Given a string that might be a namespace suffix (e.g. a sub-namespace),
     * return it with a leading backslash if it's not empty.
     */
    public static function optionalNamespaceSuffix(?string $suffix): string
    {
        return $suffix ? "\\{$suffix}" : '';
    }

    public static function resourceClassName(string $value): string
    {
        return self::safeClassName($value, 'Resource');
    }

    public static function requestClassName(string $value): string
    {
        return self::safeClassName($value, 'Request');
    }

    public static function responseClassName(string $value): string
    {
        return self::safeClassName($value, 'Response');
    }

    public static function dtoClassName(string $value): string
    {
        return self::safeClassName($value, 'Dto');
    }

    public static function connectorClassName(string $value): string
    {
        return self::safeClassName($value, 'Connector');
    }
}
