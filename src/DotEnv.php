<?php

namespace Wilkques\Serializer;

/**
 * Minimal, dependency-free ".env" (dotenv) subset parser/dumper.
 *
 * Supports full-line comments, an optional leading "export ", and
 * quoted/unquoted values (quotes are only stripped, never escape-decoded).
 * Values are returned as raw strings - no true/false/null/numeric casting,
 * matching how real .env files/shell environments work; that casting is a
 * read-time concern (see this package's `env()` helper), not a parse-time
 * one, so a parsed value stays whatever string was written.
 */
class DotEnv
{
    /**
     * @param string $contents
     *
     * @return array
     */
    public static function parse($contents)
    {
        $lines = preg_split('/\r\n|\r|\n/', $contents);

        $result = array();

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || strpos($line, '#') === 0) {
                continue;
            }

            if (strpos($line, '=') === false) {
                continue;
            }

            list($key, $value) = explode('=', $line, 2);

            $key = trim($key);

            $key = preg_replace('/^export\s+/', '', $key);

            $result[$key] = self::unquote(trim($value));
        }

        return $result;
    }

    /**
     * @param string $value
     *
     * @return string
     */
    protected static function unquote($value)
    {
        $length = strlen($value);

        if ($length > 1) {
            $first = $value[0];
            $last = $value[$length - 1];

            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                return substr($value, 1, -1);
            }
        }

        return $value;
    }

    /**
     * Inverse of parse(): dump a flat key => value array back into ".env" text.
     *
     * @param array $data
     *
     * @return string
     */
    public static function encode($data)
    {
        $output = '';

        foreach ($data as $key => $value) {
            $output .= "{$key}=" . self::encodeValue((string) $value) . "\n";
        }

        return $output;
    }

    /**
     * Only leading/trailing whitespace actually needs quoting to survive
     * parse()'s trim() - every other character is safe unquoted since
     * parse() only splits on the first "=" and only treats a line as a
     * comment when "#" is the very first character.
     *
     * @param string $value
     *
     * @return string
     */
    protected static function encodeValue($value)
    {
        if (preg_match('/^\s|\s$/', $value)) {
            return '"' . str_replace('"', '\\"', $value) . '"';
        }

        return $value;
    }
}
