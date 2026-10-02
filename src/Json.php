<?php

namespace Wilkques\Serializer;

/**
 * JSON parser/dumper - a thin wrapper around PHP's own native
 * json_decode()/json_encode() (no hand-rolled scanning needed, unlike
 * Yaml/Xml/Toml, since JSON already has reliable built-in support, the
 * same reasoning as Ini wrapping parse_ini_string()).
 *
 * Pretty-printing/unescaped-slashes/unescaped-unicode flags are applied
 * only when the running PHP defines them (PHP >= 5.4 for JSON_PRETTY_PRINT
 * and JSON_UNESCAPED_SLASHES, PHP >= 5.4 for JSON_UNESCAPED_UNICODE), so
 * encode() degrades to compact/escaped output on PHP 5.3 rather than
 * erroring on an undefined constant.
 */
class Json
{
    /**
     * @param string $contents
     *
     * @return mixed
     */
    public static function parse($contents)
    {
        $data = json_decode($contents, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Unable to parse JSON contents: ' . self::lastErrorMessage());
        }

        return $data;
    }

    /**
     * Inverse of parse(): dump a value back into JSON text.
     *
     * @param mixed $data
     *
     * @return string
     */
    public static function encode($data)
    {
        $flags = 0;

        if (defined('JSON_PRETTY_PRINT')) {
            $flags |= JSON_PRETTY_PRINT;
        }

        if (defined('JSON_UNESCAPED_SLASHES')) {
            $flags |= JSON_UNESCAPED_SLASHES;
        }

        if (defined('JSON_UNESCAPED_UNICODE')) {
            $flags |= JSON_UNESCAPED_UNICODE;
        }

        $json = json_encode($data, $flags);

        if ($json === false) {
            throw new \RuntimeException('Unable to encode data to JSON: ' . self::lastErrorMessage());
        }

        return $json;
    }

    /**
     * json_last_error_msg() only exists from PHP 5.5 - fall back to the
     * raw error code on older PHP rather than calling an undefined function.
     *
     * @return string
     */
    protected static function lastErrorMessage()
    {
        if (function_exists('json_last_error_msg')) {
            return json_last_error_msg();
        }

        return 'json_last_error() = ' . json_last_error();
    }
}
