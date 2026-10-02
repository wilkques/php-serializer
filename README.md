# Serializer for PHP

[![TESTS](https://github.com/wilkques/php-serializer/actions/workflows/github-ci.yml/badge.svg)](https://github.com/wilkques/php-serializer/actions/workflows/github-ci.yml)

English | [繁體中文](README_ZH.md)

YAML, XML, INI, TOML, JSON, and .env parsers/dumpers that turn simple config-style documents into plain PHP arrays (and back), compatible all the way back to **PHP 5.3**.

## Requirements

- PHP >= 5.3
- `wilkques/php-helper` (for `Arrays::isList()`)
- No extensions required — `Yaml`, `Xml`, and `Toml` are hand-rolled subset parsers, not wrappers around `ext-yaml`/SimpleXML/DOM/an external TOML library. `Ini` and `Json` wrap PHP's own native `parse_ini_string()`/`json_decode()`/`json_encode()`, which already have no extension dependency.

`Yaml`/`Xml`/`Toml` cover nested mappings/elements/tables, sequences/repeated elements/inline arrays, and scalar casting, but not the full spec of each format (no anchors, flow style, namespaces, array-of-tables, inline tables, dates, multi-line strings, or trailing inline comments after a value). `Xml` additionally supports attributes and CDATA: an element's attributes live under a reserved `@attributes` key, and if that element is otherwise a plain-text leaf, its text lands under a sibling `@value` key (e.g. `<root><user id="1">bob</user></root>` parses to `['user' => ['@attributes' => ['id' => '1'], '@value' => 'bob']]`; an attribute-less element is unaffected). `<![CDATA[...]]>` sections are read as plain, entity-decoded, whitespace-trimmed text on parse (comments and processing instructions, including the one inside a CDATA section, are skipped in place rather than stripped from the whole document up front, so they can't corrupt a CDATA section's content); `encode()` never re-emits CDATA, so that direction isn't a round-trip. `Xml` still doesn't support namespaces or mixed content (text alongside child elements in the same node - the text is dropped, same as before). `Xml::encode()` also rejects (throws `\RuntimeException`) a tag or attribute name that isn't a valid XML name, and an `@value` that is itself an array, rather than silently writing malformed XML or the literal word `Array`. Beyond that, `Xml::parse(Xml::encode($data)) === $data` holds for string/array-shaped `$data`, but not universally: a non-string scalar (`bool`/`int`/`float`/`null`) passed to `encode()` is stringified for convenience and always comes back as a `string` (XML has no native scalar types, the same reasoning `DotEnv` already applies), and a one-element sequence comes back as a plain scalar instead of a one-element list (inherent to XML - a lone element and a one-element list look identical on the wire). `Ini` only supports the two levels INI itself has (top-level scalars + one level of `[section]` scalars), and type round-tripping (bool/int/float) depends on `INI_SCANNER_TYPED` being available (PHP >= 5.6.1) — see the class docblock for exact behavior on older PHP. `DotEnv` parses `.env`-style `KEY=value` text (comments, `export `, quoted values) — unlike the others it never type-casts values; a parsed value is always a raw string, since type casting is meant to be a read-time concern, not a parse-time one.

`Yaml::parse()`, `Toml::parse()`, `DotEnv::parse()`, `Xml::parse()`, `Ini::parse()`, and `Json::parse()` all throw `\RuntimeException` on malformed input (a non-blank, non-comment line that doesn't match the expected grammar, or — for `Xml`/`Ini`/`Json` — input their underlying parser itself rejects) rather than silently producing a partial/garbage result. `Xml::encode()` throws too, for input it can't turn into valid XML (see above).

## Installation

```
composer require wilkques/serializer
```

## Usage

```php
use Wilkques\Serializer\Yaml;
use Wilkques\Serializer\Xml;
use Wilkques\Serializer\Ini;

Yaml::parse("abc: efg\nhij:\n  lmn: opq");
// ['abc' => 'efg', 'hij' => ['lmn' => 'opq']]

Yaml::encode(['abc' => 'efg', 'hij' => ['lmn' => 'opq']]);
// "abc: efg\nhij:\n  lmn: opq\n"

Xml::parse('<root><abc>efg</abc><hij><lmn>opq</lmn></hij></root>');
// ['abc' => 'efg', 'hij' => ['lmn' => 'opq']]

Xml::encode(['abc' => 'efg', 'hij' => ['lmn' => 'opq']]);
// <?xml version="1.0"?>\n<root>\n    <abc>efg</abc>\n    <hij>\n        <lmn>opq</lmn>\n    </hij>\n</root>\n

Xml::encode(['abc' => 'efg'], 'root', '1.1', 'UTF-8');
// <?xml version="1.1" encoding="UTF-8"?>\n<root>\n    <abc>efg</abc>\n</root>\n

Xml::parse('<user id="1" name="bob">bob@example.com</user>');
// ['@attributes' => ['id' => '1', 'name' => 'bob'], '@value' => 'bob@example.com']
// (the root tag name itself is never part of the output, same as <root> above)

Xml::encode(['user' => ['@attributes' => ['id' => '1'], 'email' => 'bob@example.com']]);
// <?xml version="1.0"?>\n<root>\n    <user id="1">\n        <email>bob@example.com</email>\n    </user>\n</root>\n

Ini::parse("abc = \"efg\"\n\n[hij]\nlmn = \"opq\"");
// ['abc' => 'efg', 'hij' => ['lmn' => 'opq']]

Ini::encode(['abc' => 'efg', 'hij' => ['lmn' => 'opq']]);
// "abc = \"efg\"\n\n[hij]\nlmn = \"opq\"\n"

use Wilkques\Serializer\DotEnv;

DotEnv::parse("APP_NAME=MyApp\nAPP_URL=\"https://example.com\"");
// ['APP_NAME' => 'MyApp', 'APP_URL' => 'https://example.com']

DotEnv::encode(['APP_NAME' => 'MyApp']);
// "APP_NAME=MyApp\n"

use Wilkques\Serializer\Toml;

Toml::parse("abc = \"efg\"\n\n[hij]\nlmn = \"opq\"");
// ['abc' => 'efg', 'hij' => ['lmn' => 'opq']]

Toml::encode(['abc' => 'efg', 'hij' => ['lmn' => 'opq'], 'tags' => ['a', 'b']]);
// "abc = \"efg\"\ntags = [\"a\", \"b\"]\n\n[hij]\nlmn = \"opq\"\n"

use Wilkques\Serializer\Json;

Json::parse('{"abc":"efg","hij":{"lmn":"opq"}}');
// ['abc' => 'efg', 'hij' => ['lmn' => 'opq']]

Json::encode(['abc' => 'efg', 'hij' => ['lmn' => 'opq']]);
// "{\n    \"abc\": \"efg\",\n    \"hij\": {\n        \"lmn\": \"opq\"\n    }\n}"
```

`encode()` is the inverse of `parse()` for this subset — `parse(encode($data)) === $data` round-trips for mappings, sequences, repeated elements, and scalar casting. Ambiguous strings (e.g. `"true"`, `"123"`, values with leading/trailing whitespace) are automatically quoted on encode so they don't get mis-cast when parsed back. `Json` round-trips natively (JSON has no ambiguous-scalar quoting problem to work around) and skips the `JSON_PRETTY_PRINT`/`JSON_UNESCAPED_SLASHES`/`JSON_UNESCAPED_UNICODE` flags on PHP < 5.4, where those constants don't exist yet.

## Testing

```
composer install
vendor/bin/phpunit -c phpunit-higher.xml   # PHP 7+
vendor/bin/phpunit -c phpunit-lower.xml    # PHP 5.3
```

## License

MIT
