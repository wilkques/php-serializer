<?php

namespace Wilkques\Serializer;

use Wilkques\Helpers\Arrays;

/**
 * Minimal, dependency-free XML subset parser/dumper (no SimpleXML/DOM).
 *
 * parse()/encode() are inverses of each other over the same subset:
 * nested elements, repeated sibling elements (grouped into/expanded
 * from a list), text content, attributes, and basic entity decoding.
 * An element's attributes (if any) are carried under a reserved
 * '@attributes' key; if that element is otherwise a leaf, its text
 * lands under a reserved '@value' key alongside it - a convention
 * used by most PHP xml-to-array helpers (e.g. <a id="1">x</a> =>
 * ['@attributes' => ['id' => '1'], '@value' => 'x']). An
 * attribute-less element keeps the plain string-or-children shape.
 * CDATA sections are read as plain, entity-decoded, whitespace-trimmed
 * text on parse() (same trimming as ordinary text content); encode()
 * never re-emits CDATA, so that direction isn't a round-trip. encode()
 * rejects a tag or attribute name that isn't a valid XML name, and an
 * '@value' that is itself an array, rather than silently writing
 * malformed XML or the literal word "Array".
 *
 * "parse(encode($data)) === $data" holds for the string/array shapes
 * parse() can actually produce, but not universally for anything you can
 * hand-build and pass to encode():
 *   - Only strings and arrays survive the round trip. encode() accepts
 *     bool/int/float/null as a stringify-for-convenience feature (e.g.
 *     bool becomes the text "true"/"false"), but parse() never casts
 *     back - a parsed leaf or attribute value is always a string, the
 *     same stance DotEnv takes and for the same reason: XML text and
 *     attribute values have no native scalar types in the spec itself.
 *   - A one-element sequence doesn't round-trip: parse() can only tell a
 *     repeated tag is a list by seeing it more than once, so encoding a
 *     single-item list produces one sibling tag, which re-parses as a
 *     plain scalar, not a one-element list. This is inherent to XML, not
 *     a bug - no xml-to-array convention can tell a lone element from a
 *     one-element list by looking at the markup alone.
 *   - parse() only ever returns an array; a root element that's a plain
 *     text leaf (no children) parses to an empty array, since there's
 *     nowhere in a flat key-value array to put "the root is itself a
 *     string".
 *
 * It does not support namespaces, mixed content (text sitting
 * alongside child elements - the text is dropped, same as before),
 * or malformed-XML recovery - just enough to turn a typical XML
 * document into a nested array, the same minimal spirit as the Yaml
 * parser.
 */
class Xml
{
    /**
     * @param string $contents
     *
     * @return array
     *
     * @throws \RuntimeException if an opening or closing tag can't be matched.
     */
    public static function parse($contents)
    {
        $contents = trim($contents);

        $pos = 0;

        // Skip the prolog (whitespace, the XML declaration, and
        // any comments/instructions before the root tag) one piece at a
        // time, rather than stripping comments/declarations from the
        // whole document up front - that would also strip one written
        // inside a CDATA section, corrupting its text.
        while ($pos < strlen($contents)) {
            $skipped = strspn($contents, " \t\r\n", $pos);

            if ($skipped > 0) {
                $pos += $skipped;

                continue;
            }

            if (self::skipCommentOrInstruction($contents, $pos)) {
                continue;
            }

            break;
        }

        list(, $value) = self::parseElement($contents, $pos);

        return is_array($value) ? $value : array();
    }

    /**
     * If a comment or processing instruction (including the XML
     * declaration) starts at $pos, advance $pos past it and return true.
     * Otherwise leave $pos untouched and return false. Unlike
     * whitespace, a comment/instruction is only ever skipped here, at the
     * exact position it's met - never eaten along with surrounding
     * whitespace - so it doesn't disturb whitespace that's otherwise part
     * of a text node (e.g. "a <!--c--> b" stays "a  b", not "a b").
     *
     * @param string $contents
     * @param int $pos
     *
     * @return bool
     *
     * @throws \RuntimeException if a comment/instruction is unterminated.
     */
    protected static function skipCommentOrInstruction($contents, &$pos)
    {
        if (substr($contents, $pos, 4) === '<!--') {
            $end = strpos($contents, '-->', $pos);

            if ($end === false) {
                throw new \RuntimeException('Malformed XML: unterminated comment near position ' . $pos);
            }

            $pos = $end + 3;

            return true;
        }

        if (substr($contents, $pos, 2) === '<?') {
            $end = strpos($contents, '?>', $pos);

            if ($end === false) {
                throw new \RuntimeException('Malformed XML: unterminated processing instruction near position ' . $pos);
            }

            $pos = $end + 2;

            return true;
        }

        return false;
    }

    /**
     * @param string $contents
     * @param int $pos
     *
     * @return array [tagName, value]
     */
    protected static function parseElement($contents, &$pos)
    {
        $pattern = '/\G<([a-zA-Z_][\w\-.:]*)((?:\s+[a-zA-Z_][\w\-.:]*\s*=\s*(?:"[^"]*"|\'[^\']*\'))*)\s*(\/)?>/';

        if (!preg_match($pattern, $contents, $m, 0, $pos)) {
            throw new \RuntimeException('Malformed XML near position ' . $pos);
        }

        $tag = $m[1];

        $attributes = self::parseAttributes($m[2]);

        $pos += strlen($m[0]);

        if (isset($m[3]) && $m[3] === '/') {
            return array($tag, self::withAttributes($attributes, ''));
        }

        $pairs = array();

        $text = '';

        while (true) {
            if ($pos >= strlen($contents)) {
                throw new \RuntimeException('Malformed XML: unclosed tag <' . $tag . '>');
            }

            if (substr($contents, $pos, 2) === '</') {
                if (!preg_match('/\G<\/[a-zA-Z_][\w\-.:]*\s*>/', $contents, $closing, 0, $pos)) {
                    throw new \RuntimeException('Malformed XML near position ' . $pos);
                }

                $pos += strlen($closing[0]);

                break;
            }

            if (self::skipCommentOrInstruction($contents, $pos)) {
                continue;
            }

            if (substr($contents, $pos, 9) === '<![CDATA[') {
                if (!preg_match('/\G<!\[CDATA\[(.*?)\]\]>/s', $contents, $cdata, 0, $pos)) {
                    throw new \RuntimeException('Malformed XML: unterminated CDATA section near position ' . $pos);
                }

                // Re-escape raw CDATA content so the single decodeEntities()
                // pass below (over the whole accumulated $text) restores it
                // literally instead of treating it as entity-encoded text.
                $text .= self::encodeEntities($cdata[1]);

                $pos += strlen($cdata[0]);

                continue;
            }

            if ($contents[$pos] === '<') {
                $pairs[] = self::parseElement($contents, $pos);

                continue;
            }

            $nextTagPos = strpos($contents, '<', $pos);

            if ($nextTagPos === false) {
                throw new \RuntimeException('Malformed XML: unclosed tag <' . $tag . '>');
            }

            $text .= substr($contents, $pos, $nextTagPos - $pos);

            $pos = $nextTagPos;
        }

        if (empty($pairs)) {
            return array($tag, self::withAttributes($attributes, self::decodeEntities(trim($text))));
        }

        return array($tag, self::withAttributes($attributes, self::groupChildren($pairs)));
    }

    /**
     * @param string $attributeString raw text between the tag name and the
     *     closing "/"/">", e.g. ' id="1" name="bob"'
     *
     * @return array<string, string>
     */
    protected static function parseAttributes($attributeString)
    {
        $attributes = array();

        if (trim($attributeString) === '') {
            return $attributes;
        }

        preg_match_all('/([a-zA-Z_][\w\-.:]*)\s*=\s*(["\'])(.*?)\2/s', $attributeString, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $attributes[$match[1]] = self::decodeEntities($match[3]);
        }

        return $attributes;
    }

    /**
     * Fold parsed attributes into an element's value under the
     * reserved '@attributes' key (plus '@value' for an otherwise-leaf
     * element), or return $value unchanged if there are none.
     *
     * @param array $attributes
     * @param mixed $value
     *
     * @return mixed
     */
    protected static function withAttributes($attributes, $value)
    {
        if (empty($attributes)) {
            return $value;
        }

        if (is_array($value)) {
            return array('@attributes' => $attributes) + $value;
        }

        return array('@attributes' => $attributes, '@value' => $value);
    }

    /**
     * Inverse of parse(): dump an array back into this parser's XML subset.
     *
     * parse() discards the whole XML declaration unread, so
     * $version/$encoding only affect the declaration text that gets
     * written out - they have no effect on how this class itself parses.
     *
     * @param array $data
     * @param string $rootTag
     * @param string $version
     * @param string|null $encoding
     *
     * @return string
     *
     * @throws \RuntimeException if $rootTag, an array key used as a tag
     *     name, or an '@attributes' key isn't a valid XML name, or if an
     *     '@value' is itself an array.
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
     *
     * @throws \RuntimeException if $tag (or, recursively, any attribute or
     *     child tag name) isn't a valid XML name.
     */
    protected static function encodeElement($tag, $value, $depth)
    {
        self::validateName($tag);

        $indent = str_repeat('    ', $depth);

        if (is_array($value) && Arrays::exists($value, '@attributes')) {
            return self::encodeElementWithAttributes($tag, $value, $depth);
        }

        if (is_array($value)) {
            return "{$indent}<{$tag}>\n" . self::encodeChildren($value, $depth) . "{$indent}</{$tag}>\n";
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
     * @param array $value child elements keyed by tag name (a list value
     *     under a key means that tag repeats)
     * @param int $depth
     *
     * @return string
     */
    protected static function encodeChildren($value, $depth)
    {
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

        return $children;
    }

    /**
     * Encode an element whose value carries a reserved '@attributes' key
     * (and optionally '@value' for an otherwise-leaf element) - the
     * inverse of withAttributes().
     *
     * @param string $tag
     * @param array $value
     * @param int $depth
     *
     * @return string
     */
    protected static function encodeElementWithAttributes($tag, $value, $depth)
    {
        $indent = str_repeat('    ', $depth);

        $attrString = self::encodeAttributes($value['@attributes']);

        unset($value['@attributes']);

        $hasText = Arrays::exists($value, '@value');

        $text = $hasText ? $value['@value'] : null;

        unset($value['@value']);

        if (!empty($value)) {
            return "{$indent}<{$tag}{$attrString}>\n" . self::encodeChildren($value, $depth) . "{$indent}</{$tag}>\n";
        }

        if ($text === '' || $text === null) {
            return "{$indent}<{$tag}{$attrString}/>\n";
        }

        if (is_array($text)) {
            throw new \RuntimeException("Cannot encode an array as the '@value' of <{$tag}>");
        }

        if (is_bool($text)) {
            $text = $text ? 'true' : 'false';
        }

        return "{$indent}<{$tag}{$attrString}>" . self::encodeEntities($text) . "</{$tag}>\n";
    }

    /**
     * @param array<string, string> $attributes
     *
     * @return string e.g. ' id="1" name="bob"', or '' if empty
     *
     * @throws \RuntimeException if an attribute name isn't a valid XML name.
     */
    protected static function encodeAttributes($attributes)
    {
        $output = '';

        foreach ($attributes as $name => $value) {
            self::validateName($name);

            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }

            $output .= ' ' . $name . '="' . self::encodeEntities($value) . '"';
        }

        return $output;
    }

    /**
     * @param string $name a tag or attribute name
     *
     * @return void
     *
     * @throws \RuntimeException if $name isn't a valid XML name - the same
     *     grammar parseElement()/parseAttributes() accept on the way in.
     */
    protected static function validateName($name)
    {
        if (!preg_match('/^[a-zA-Z_][\w\-.:]*$/', (string) $name)) {
            throw new \RuntimeException('Invalid XML tag/attribute name: ' . var_export($name, true));
        }
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
