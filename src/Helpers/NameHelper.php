<?php

namespace Crescat\SaloonSdkGenerator\Helpers;

use Illuminate\Support\Str;

class NameHelper
{
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
            ->trim();
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
    public static function safeClassName(string $value): string
    {
        if (isset(self::$classNameCache[$value])) {
            return self::$classNameCache[$value];
        }

        $result = Str::studly(self::normalize($value));
        self::$classNameCache[$value] = $result;

        return $result;
    }
}
