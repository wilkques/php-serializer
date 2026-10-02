<?php

namespace Wilkques\Serializer;

use Wilkques\Helpers\Arrays;

/**
 * Minimal, dependency-free YAML subset parser/dumper.
 *
 * parse()/encode() are inverses of each other over the same subset:
 * nested mappings, nested sequences (including sequences of mappings),
 * full-line comments (parse only), and scalar casting (quoted strings,
 * true/false/null, integers, floats). It does not implement the full
 * YAML spec (no anchors/aliases, flow style, multi-document, or
 * multi-line scalars) - just enough for typical config files, so this
 * package doesn't need the optional "yaml" PHP extension.
 */
class Yaml
{
    /**
     * @param string $contents
     *
     * @return array
     *
     * @throws \RuntimeException if a non-blank, non-comment line is
     *     neither a sequence item ("- ...") nor a "key: value" mapping.
     */
    public static function parse($contents)
    {
        $lines = self::tokenize($contents);

        $index = 0;

        return self::parseBlock($lines, $index, 0);
    }

    /**
     * Inverse of parse(): dump an array back into this parser's YAML subset.
     *
     * @param array $data
     *
     * @return string
     */
    public static function encode($data)
    {
        return self::encodeBlock($data, 0);
    }

    /**
     * @param array $data
     * @param int $indent
     *
     * @return string
     */
    protected static function encodeBlock($data, $indent)
    {
        if (empty($data)) {
            return '';
        }

        $pad = str_repeat(' ', $indent);

        $output = '';

        if (Arrays::isList($data)) {
            foreach ($data as $item) {
                if (is_array($item)) {
                    $output .= self::encodeSequenceMappingItem($item, $indent);
                } else {
                    $output .= "{$pad}- " . self::encodeScalar($item) . "\n";
                }
            }

            return $output;
        }

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                // encodeBlock() already returns '' for an empty array, so
                // this naturally degrades to "key:\n" (round-trips as null -
                // flow style "[]"/"{}" isn't supported by parse()).
                $output .= "{$pad}{$key}:\n" . self::encodeBlock($value, $indent + 2);
            } else {
                $output .= "{$pad}{$key}: " . self::encodeScalar($value) . "\n";
            }
        }

        return $output;
    }

    /**
     * Encode "- key: value" plus any continuation lines for a sequence item
     * that is itself a mapping, aligned to where "key" starts (indent + 2).
     *
     * @param array $item
     * @param int $indent
     *
     * @return string
     */
    protected static function encodeSequenceMappingItem($item, $indent)
    {
        $pad = str_repeat(' ', $indent);

        $output = '';

        $first = true;

        foreach ($item as $key => $value) {
            $prefix = $first ? "{$pad}- " : "{$pad}  ";

            if (is_array($value)) {
                $output .= "{$prefix}{$key}:\n" . self::encodeBlock($value, $indent + 4);
            } else {
                $output .= "{$prefix}{$key}: " . self::encodeScalar($value) . "\n";
            }

            $first = false;
        }

        return $output;
    }

    /**
     * @param mixed $value
     *
     * @return string
     */
    protected static function encodeScalar($value)
    {
        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        $value = (string) $value;

        if ($value === '' || self::needsQuoting($value)) {
            return self::quote($value);
        }

        return $value;
    }

    /**
     * Whether a plain (unquoted) scalar would round-trip through
     * castScalar() unchanged - if not, it must be quoted.
     *
     * @param string $value
     *
     * @return bool
     */
    protected static function needsQuoting($value)
    {
        if (preg_match('/^\s|\s$/', $value)) {
            return true;
        }

        if (preg_match('/^(true|false|null|~)$/i', $value)) {
            return true;
        }

        if (is_numeric($value)) {
            return true;
        }

        return false;
    }

    /**
     * This parser has no escape-sequence support, so pick whichever quote
     * character doesn't already appear in the value.
     *
     * @param string $value
     *
     * @return string
     */
    protected static function quote($value)
    {
        if (strpos($value, '"') === false) {
            return '"' . $value . '"';
        }

        if (strpos($value, "'") === false) {
            return "'" . $value . "'";
        }

        return '"' . $value . '"';
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

            $indent = strlen($rawLine) - strlen(ltrim($rawLine, ' '));

            $lines[] = array('indent' => $indent, 'content' => $trimmed);
        }

        return $lines;
    }

    /**
     * @param array $lines
     * @param int $index
     * @param int $indent
     *
     * @return array
     */
    protected static function parseBlock($lines, &$index, $indent)
    {
        $result = array();

        while ($index < count($lines) && $lines[$index]['indent'] >= $indent) {
            $line = $lines[$index];

            if ($line['indent'] > $indent) {
                break;
            }

            if ($line['content'] === '-' || strpos($line['content'], '- ') === 0) {
                $result[] = self::parseSequenceItem($lines, $index, $indent);

                continue;
            }

            list($key, $value) = self::parseMappingLine($lines, $index, $indent);

            $result[$key] = $value;
        }

        return $result;
    }

    /**
     * @param array $lines
     * @param int $index
     * @param int $indent
     *
     * @return mixed
     */
    protected static function parseSequenceItem($lines, &$index, $indent)
    {
        $line = $lines[$index];

        $itemContent = $line['content'] === '-' ? '' : substr($line['content'], 2);

        $index++;

        if ($itemContent === '') {
            $nextIndent = isset($lines[$index]) ? $lines[$index]['indent'] : null;

            if ($nextIndent === null || $nextIndent <= $indent) {
                return null;
            }

            return self::parseBlock($lines, $index, $nextIndent);
        }

        // "- key: value" starts a mapping item; sibling keys are continuation
        // lines indented to line up with where "key" started (indent + 2).
        if (self::looksLikeMappingLine($itemContent)) {
            list($key, $value) = self::splitMappingLine($itemContent, $lines, $index, $indent + 2);

            $item = array($key => $value);

            while ($index < count($lines) && $lines[$index]['indent'] === $indent + 2) {
                list($key, $value) = self::parseMappingLine($lines, $index, $indent + 2);

                $item[$key] = $value;
            }

            return $item;
        }

        return self::castScalar($itemContent);
    }

    /**
     * @param array $lines
     * @param int $index
     * @param int $indent
     *
     * @return array [key, value]
     */
    protected static function parseMappingLine($lines, &$index, $indent)
    {
        $content = $lines[$index]['content'];

        $index++;

        return self::splitMappingLine($content, $lines, $index, $indent);
    }

    /**
     * @param string $content line content without leading indentation, e.g. "key: value"
     * @param array $lines
     * @param int $index
     * @param int $indent indentation of the (possibly nested) value block
     *
     * @return array [key, value]
     */
    protected static function splitMappingLine($content, $lines, &$index, $indent)
    {
        $colonPosition = strpos($content, ':');

        if ($colonPosition === false) {
            throw new \RuntimeException('Malformed YAML line (expected "key: value" or "- item"): ' . $content);
        }

        $key = trim(substr($content, 0, $colonPosition));

        $value = trim(substr($content, $colonPosition + 1));

        if ($value !== '') {
            return array($key, self::castScalar($value));
        }

        $nextIndent = isset($lines[$index]) ? $lines[$index]['indent'] : null;

        if ($nextIndent === null || $nextIndent <= $indent) {
            return array($key, null);
        }

        return array($key, self::parseBlock($lines, $index, $nextIndent));
    }

    /**
     * @param string $content
     *
     * @return bool
     */
    protected static function looksLikeMappingLine($content)
    {
        $colonPosition = strpos($content, ':');

        if ($colonPosition === false) {
            return false;
        }

        $afterColon = substr($content, $colonPosition + 1);

        return $afterColon === '' || $afterColon[0] === ' ';
    }

    /**
     * @param string $value
     *
     * @return int|float|bool|string|null
     */
    protected static function castScalar($value)
    {
        $length = strlen($value);

        if ($length > 1) {
            $first = $value[0];
            $last = $value[$length - 1];

            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                return substr($value, 1, -1);
            }
        }

        switch (strtolower($value)) {
            case 'true':
                return true;
            case 'false':
                return false;
            case 'null':
            case '~':
                return null;
        }

        if (is_numeric($value)) {
            return strpos($value, '.') !== false ? (float) $value : (int) $value;
        }

        return $value;
    }
}
