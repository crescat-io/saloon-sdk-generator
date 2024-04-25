<?php

declare(strict_types=1);

namespace Crescat\SaloonSdkGenerator\Helpers;

use Crescat\SaloonSdkGenerator\Enums\SimpleType;
use Illuminate\Support\Arr;
use Nette\PhpGenerator\PhpFile;

class Utils
{
    public static function formatNamespaceAndClass(PhpFile $file): string
    {
        return sprintf(
            '%s@%s',
            Arr::first($file->getNamespaces())->getName(),
            Arr::first($file->getClasses())->getName()
        );
    }

    /**
     * Wrap long lines of text to a specified maximum line length.
     *
     * @param  string  $inputString  The input string to be wrapped.
     * @param  int  $maxLineLength  The maximum length of each line (default: 100).
     * @return string The input string with newlines inserted as needed.
     */
    public static function wrapLongLines(string $inputString, int $maxLineLength = 100): string
    {
        $outputString = '';
        $currentLine = '';

        $words = explode(' ', $inputString);

        foreach ($words as $word) {
            if (strlen($currentLine.' '.$word) <= $maxLineLength) {
                if (! empty($currentLine)) {
                    $currentLine .= ' ';
                }
                $currentLine .= $word;
            } else {
                $outputString .= $currentLine."\n";
                $currentLine = $word;
            }
        }

        if (! empty($currentLine)) {
            $outputString .= $currentLine;
        }

        return $outputString;
    }

    public static function parseNestedStringToArray($input): array
    {
        $result = [];
        $parts = preg_split('/\]\[|\[|\]/', $input, -1, PREG_SPLIT_NO_EMPTY);

        $temp = &$result;

        foreach ($parts as $part) {
            if (! isset($temp[$part])) {
                $temp[$part] = [];
            }
            $temp = &$temp[$part];
        }

        return $result;
    }

    public static function isBuiltinType(string $type): bool
    {
        return SimpleType::tryFrom($type) !== null || $type === 'DateTime';
    }
}
