<?php

namespace App;

class Utils
{
    /**
     * Wrap long lines of text to a specified maximum line length.
     *
     * @param string $inputString The input string to be wrapped.
     * @param int $maxLineLength The maximum length of each line (default: 100).
     * @return string The input string with newlines inserted as needed.
     */
    public static function wrapLongLines(string $inputString, int $maxLineLength = 100): string
    {
        $outputString = '';
        $currentLine = '';

        $words = explode(' ', $inputString);

        foreach ($words as $word) {
            if (strlen($currentLine . ' ' . $word) <= $maxLineLength) {
                if (!empty($currentLine)) {
                    $currentLine .= ' ';
                }
                $currentLine .= $word;
            } else {
                $outputString .= $currentLine . "\n";
                $currentLine = $word;
            }
        }

        if (!empty($currentLine)) {
            $outputString .= $currentLine;
        }

        return $outputString;
    }

    public static function parseNestedStringToArray($input): string
    {
        $result = [];
        $parts = preg_split('/\]\[|\[|\]/', $input, -1, PREG_SPLIT_NO_EMPTY);

        $temp = &$result;

        foreach ($parts as $part) {
            if (!isset($temp[$part])) {
                $temp[$part] = [];
            }
            $temp = &$temp[$part];
        }

        return $result;
    }

    /**
     * Recursively extracts expected types from the given data structure.
     *
     * @param array $data The data structure to extract types from.
     * @return array An array containing expected types for each key in the data structure.
     */
    public static function extractExpectedTypes(?array $data): array
    {
        if (!$data) return [];

        $expectedTypes = [];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                // Recurse into array
                $expectedTypes[$key] = self::extractExpectedTypes($value);

            } elseif (is_string($value) && preg_match('/^<([^>]+)>$/', $value, $matches)) {
                // The regex '/^<([^>]+)>$/' matches strings enclosed in angle brackets (<...>).
                // It captures the content inside the brackets as $matches[1].
                // Examples:
                // - "<string>" matches and $matches[1] will be "string"
                // - "<integer,null>" matches and $matches[1] will be "integer,null"
                $expectedTypes[$key] = explode(',', $matches[1]);
            } else {
                // If the value is not a string in <...> format, use gettype to determine the type.
                $expectedTypes[$key] = gettype($value);
            }
        }

        return $expectedTypes;
    }
}
