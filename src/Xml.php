<?php

namespace Wilkques\Serializer;

use Wilkques\Helpers\Arrays;

/**
 * Minimal, dependency-free XML subset parser/dumper (no SimpleXML/DOM).
 *
 * parse()/encode() are inverses of each other over the same subset:
 * nested elements, repeated sibling elements (grouped into/expanded
 * from a list), text content, and basic entity decoding. It does not
 * support attributes, CDATA, namespaces, or malformed-XML recovery -
 * just enough to turn a simple config-style XML document into a
 * nested array, the same minimal spirit as the Yaml parser.
 */
class Xml
{
    /**
     * @param string $contents
     *
     * @return array
     */
    public static function parse($contents)
    {
        $contents = preg_replace('/<\?xml[^>]*\?>/', '', $contents);
        $contents = preg_replace('/<!--.*?-->/s', '', $contents);
        $contents = trim($contents);

        $pos = 0;

        list(, $value) = self::parseElement($contents, $pos);

        return is_array($value) ? $value : array();
    }

    /**
     * @param string $contents
     * @param int $pos
     *
     * @return array [tagName, value]
     */
    protected static function parseElement($contents, &$pos)
    {
        if (!preg_match('/<([a-zA-Z_][\w\-.:]*)[^>]*?(\/)?>/', $contents, $m, 0, $pos)) {
            throw new \RuntimeException('Malformed XML near position ' . $pos);
        }

        $tag = $m[1];

        $pos += strlen($m[0]);

        if (isset($m[2]) && $m[2] === '/') {
            return array($tag, '');
        }

        $pairs = array();

        $text = '';

        while (true) {
            if (substr($contents, $pos, 2) === '</') {
                if (!preg_match('/<\/[a-zA-Z_][\w\-.:]*\s*>/', $contents, $closing, 0, $pos)) {
                    throw new \RuntimeException('Malformed XML near position ' . $pos);
                }

                $pos += strlen($closing[0]);

                break;
            }

            if ($contents[$pos] === '<') {
                $pairs[] = self::parseElement($contents, $pos);

                continue;
            }

            $nextTagPos = strpos($contents, '<', $pos);

            $text .= substr($contents, $pos, $nextTagPos - $pos);

            $pos = $nextTagPos;
        }

        if (empty($pairs)) {
            return array($tag, self::decodeEntities(trim($text)));
        }

        return array($tag, self::groupChildren($pairs));
    }

    /**
     * Inverse of parse(): dump an array back into this parser's XML subset.
     *
     * parse() discards the whole <?xml ...?> declaration unread, so
     * $version/$encoding only affect the declaration text that gets
     * written out - they have no effect on how this class itself parses.
     *
     * @param array $data
     * @param string $rootTag
     * @param string $version
     * @param string|null $encoding
     *
     * @return string
     */
    public static function encode($data, $rootTag = 'root', $version = '1.0', $encoding = null)
    {
        $declaration = $encoding === null
            ? "<?xml version=\"{$version}\"?>"
            : "<?xml version=\"{$version}\" encoding=\"{$encoding}\"?>";

        return $declaration . "\n" . self::encodeElement($rootTag, $data, 0);
    }

    /**
     * @param string $tag
     * @param mixed $value
     * @param int $depth
     *
     * @return string
     */
    protected static function encodeElement($tag, $value, $depth)
    {
        $indent = str_repeat('    ', $depth);

        if (is_array($value)) {
            $children = '';

            foreach ($value as $key => $child) {
                if (is_array($child) && Arrays::isList($child)) {
                    foreach ($child as $item) {
                        $children .= self::encodeElement($key, $item, $depth + 1);
                    }
                } else {
                    $children .= self::encodeElement($key, $child, $depth + 1);
                }
            }

            return "{$indent}<{$tag}>\n{$children}{$indent}</{$tag}>\n";
        }

        if ($value === '' || $value === null) {
            return "{$indent}<{$tag}/>\n";
        }

        if (is_bool($value)) {
            $value = $value ? 'true' : 'false';
        }

        return "{$indent}<{$tag}>" . self::encodeEntities($value) . "</{$tag}>\n";
    }

    /**
     * @param string $text
     *
     * @return string
     */
    protected static function encodeEntities($text)
    {
        $text = str_replace('&', '&amp;', (string) $text);

        return str_replace(
            array('<', '>', '"', "'"),
            array('&lt;', '&gt;', '&quot;', '&apos;'),
            $text
        );
    }

    /**
     * @param array $pairs list of [tagName, value]
     *
     * @return array
     */
    protected static function groupChildren($pairs)
    {
        $counts = array();

        foreach ($pairs as $pair) {
            $counts[$pair[0]] = isset($counts[$pair[0]]) ? $counts[$pair[0]] + 1 : 1;
        }

        $children = array();

        foreach ($pairs as $pair) {
            list($tag, $value) = $pair;

            if ($counts[$tag] > 1) {
                $children[$tag][] = $value;
            } else {
                $children[$tag] = $value;
            }
        }

        return $children;
    }

    /**
     * @param string $text
     *
     * @return string
     */
    protected static function decodeEntities($text)
    {
        // &amp; must be decoded last, otherwise "&amp;lt;" would wrongly
        // turn into "<" instead of the literal text "&lt;".
        $text = str_replace(
            array('&lt;', '&gt;', '&quot;', '&apos;'),
            array('<', '>', '"', "'"),
            $text
        );

        return str_replace('&amp;', '&', $text);
    }
}
