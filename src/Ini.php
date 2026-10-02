<?php

namespace Wilkques\Serializer;

/**
 * INI parser/dumper - a thin wrapper around PHP's own native
 * parse_ini_string() (no hand-rolled scanning needed, unlike Yaml/Xml,
 * since INI has reliable built-in support), plus a hand-rolled encode()
 * since PHP has no native inverse of parse_ini_string().
 *
 * Only supports the two levels INI itself has: top-level scalars, and
 * one level of [section] scalars - no further nesting.
 *
 * Type round-tripping depends on the native scanner, not this class:
 * - PHP >= 5.6.1 (INI_SCANNER_TYPED available): bool/int/float survive
 *   parse(encode($x))), but a null value always comes back as '' (empty
 *   string) - PHP's own scanner has no NULL representation for an empty
 *   value under TYPED mode either.
 * - PHP < 5.6.1 (TYPED unavailable, falls back to NORMAL): everything
 *   comes back as a string - true/1 become "1", false/null become "".
 */
class Ini
{
    /**
     * @param string $contents
     *
     * @return array
     */
    public static function parse($contents)
    {
        // INI_SCANNER_TYPED only exists from PHP 5.6.1 - calling with it on
        // older versions would error, so fall back to NORMAL (all strings).
        $mode = defined('INI_SCANNER_TYPED') ? INI_SCANNER_TYPED : INI_SCANNER_NORMAL;

        $data = parse_ini_string($contents, true, $mode);

        if ($data === false) {
            throw new \RuntimeException('Unable to parse INI contents');
        }

        return $data;
    }

    /**
     * Inverse of parse(): dump an array back into INI text.
     *
     * @param array $data
     *
     * @return string
     */
    public static function encode($data)
    {
        $output = '';

        $sections = '';

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $sections .= "[{$key}]\n";

                foreach ($value as $subKey => $subValue) {
                    $sections .= "{$subKey} = " . self::encodeValue($subValue) . "\n";
                }

                $sections .= "\n";
            } else {
                $output .= "{$key} = " . self::encodeValue($value) . "\n";
            }
        }

        return rtrim($output . "\n" . $sections) . "\n";
    }

    /**
     * Values are always quoted so round-tripping through parse() can't
     * mis-cast a plain string that happens to look like a keyword/number
     * (INI's typed scanner treats true/false/null/numeric specially, but
     * leaves quoted values alone).
     *
     * @param mixed $value
     *
     * @return string
     */
    protected static function encodeValue($value)
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return '"' . str_replace('"', '\\"', (string) $value) . '"';
    }
}
