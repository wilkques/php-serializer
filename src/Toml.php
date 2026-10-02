<?php

namespace Wilkques\Serializer;

use Wilkques\Helpers\Arrays;

/**
 * Minimal, dependency-free TOML subset parser/dumper.
 *
 * Supports top-level key = value pairs, [table] / [table.nested] headers
 * (dotted keys build nested arrays), full-line comments, double-quoted
 * strings (with \" and \\ escapes), true/false, integers, floats, and
 * single-level inline arrays of those scalars (e.g. `a = [1, 2, "x"]`).
 * It does not implement the full TOML spec (no array-of-tables `[[...]]`,
 * inline tables `{...}`, single/triple-quoted or multi-line strings,
 * dates, or nested inline arrays) - just enough for typical config files,
 * so this package doesn't need an external TOML library (PHP has none
 * built in).
 */
class Toml
{
    /**
     * @param string $contents
     *
     * @return array
     */
    public static function parse($contents)
    {
        $result = array();

        $cursor = &$result;

        foreach (self::tokenize($contents) as $line) {
            if (preg_match('/^\[([^\]]+)\]$/', $line, $m)) {
                $cursor = &self::resolveTable($result, trim($m[1]));

                continue;
            }

            $equalsPosition = strpos($line, '=');

            if ($equalsPosition === false) {
                continue;
            }

            $key = trim(substr($line, 0, $equalsPosition));

            $value = trim(substr($line, $equalsPosition + 1));

            $cursor[$key] = self::parseValue($value);
        }

        return $result;
    }

    /**
     * @param string $contents
     *
     * @return array
     */
    protected static function tokenize($contents)
    {
        $rawLines = preg_split('/\r\n|\r|\n/', $contents);

        $lines = array();

        foreach ($rawLines as $rawLine) {
            $trimmed = trim($rawLine);

            if ($trimmed === '' || $trimmed[0] === '#') {
                continue;
            }

            $lines[] = $trimmed;
        }

        return $lines;
    }

    /**
     * Walk/create the nested array for a dotted table header, e.g.
     * "a.b" under $result builds/returns $result['a']['b'] by reference.
     *
     * @param array $result
     * @param string $path
     *
     * @return array
     */
    protected static function &resolveTable(&$result, $path)
    {
        $cursor = &$result;

        foreach (explode('.', $path) as $segment) {
            $segment = trim($segment);

            if (!isset($cursor[$segment]) || !is_array($cursor[$segment])) {
                $cursor[$segment] = array();
            }

            $cursor = &$cursor[$segment];
        }

        return $cursor;
    }

    /**
     * @param string $value
     *
     * @return mixed
     */
    protected static function parseValue($value)
    {
        $length = strlen($value);

        if ($length > 1 && $value[0] === '[' && $value[$length - 1] === ']') {
            $inner = trim(substr($value, 1, -1));

            if ($inner === '') {
                return array();
            }

            $items = array();

            foreach (self::splitTopLevel($inner) as $item) {
                $items[] = self::parseScalar(trim($item));
            }

            return $items;
        }

        return self::parseScalar($value);
    }

    /**
     * Split "a, "b, c", d" on top-level commas only - a comma inside a
     * double-quoted string doesn't end the item.
     *
     * @param string $contents
     *
     * @return array
     */
    protected static function splitTopLevel($contents)
    {
        $items = array();

        $current = '';

        $inQuotes = false;

        $length = strlen($contents);

        for ($i = 0; $i < $length; $i++) {
            $char = $contents[$i];

            if ($char === '"' && ($i === 0 || $contents[$i - 1] !== '\\')) {
                $inQuotes = !$inQuotes;
            }

            if ($char === ',' && !$inQuotes) {
                $items[] = $current;

                $current = '';

                continue;
            }

            $current .= $char;
        }

        $items[] = $current;

        return $items;
    }

    /**
     * @param string $value
     *
     * @return int|float|bool|string
     */
    protected static function parseScalar($value)
    {
        $length = strlen($value);

        if ($length > 1 && $value[0] === '"' && $value[$length - 1] === '"') {
            $inner = substr($value, 1, -1);

            // Reverse of encodeScalar()'s escaping order: unescape \" before
            // unescaping \\, otherwise a literal \" would be mangled.
            $inner = str_replace('\\"', '"', $inner);

            return str_replace('\\\\', '\\', $inner);
        }

        switch (strtolower($value)) {
            case 'true':
                return true;
            case 'false':
                return false;
        }

        if (is_numeric($value)) {
            return strpos($value, '.') !== false ? (float) $value : (int) $value;
        }

        return $value;
    }

    /**
     * Inverse of parse(): dump an array back into this parser's TOML subset.
     *
     * @param array $data
     *
     * @return string
     */
    public static function encode($data)
    {
        return rtrim(self::encodeTable($data, array())) . "\n";
    }

    /**
     * @param array $data
     * @param array $prefix
     *
     * @return string
     */
    protected static function encodeTable($data, $prefix)
    {
        $output = '';

        $tables = '';

        foreach ($data as $key => $value) {
            if (is_array($value) && !empty($value) && !Arrays::isList($value)) {
                $tables .= self::encodeTable($value, array_merge($prefix, array($key)));
            } elseif (is_array($value) && Arrays::isList($value)) {
                $output .= "{$key} = " . self::encodeArray($value) . "\n";
            } else {
                $output .= "{$key} = " . self::encodeScalar($value) . "\n";
            }
        }

        $header = empty($prefix) ? '' : '[' . implode('.', $prefix) . "]\n";

        return $header . $output . "\n" . $tables;
    }

    /**
     * @param array $value
     *
     * @return string
     */
    protected static function encodeArray($value)
    {
        $items = array();

        foreach ($value as $item) {
            $items[] = self::encodeScalar($item);
        }

        return '[' . implode(', ', $items) . ']';
    }

    /**
     * @param mixed $value
     *
     * @return string
     */
    protected static function encodeScalar($value)
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        $value = str_replace('\\', '\\\\', (string) $value);

        $value = str_replace('"', '\\"', $value);

        return '"' . $value . '"';
    }
}
