# Serializer for PHP

English | [繁體中文](README_ZH.md)

YAML, XML, INI, TOML, and .env parsers/dumpers that turn simple config-style documents into plain PHP arrays (and back), compatible all the way back to **PHP 5.3**.

## Requirements

- PHP >= 5.3
- `wilkques/php-helper` (for `Arrays::isList()`)
- No extensions required — `Yaml`, `Xml`, and `Toml` are hand-rolled subset parsers, not wrappers around `ext-yaml`/SimpleXML/DOM/an external TOML library. `Ini` wraps PHP's own native `parse_ini_string()`, which already has no extension dependency.

`Yaml`/`Xml`/`Toml` cover nested mappings/elements/tables, sequences/repeated elements/inline arrays, and scalar casting, but not the full spec of each format (no anchors, flow style, attributes, CDATA, namespaces, array-of-tables, inline tables, dates, or multi-line strings). `Ini` only supports the two levels INI itself has (top-level scalars + one level of `[section]` scalars), and type round-tripping (bool/int/float) depends on `INI_SCANNER_TYPED` being available (PHP >= 5.6.1) — see the class docblock for exact behavior on older PHP. `DotEnv` parses `.env`-style `KEY=value` text (comments, `export `, quoted values) — unlike the others it never type-casts values; a parsed value is always a raw string, since type casting is meant to be a read-time concern, not a parse-time one.

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
```

`encode()` is the inverse of `parse()` for this subset — `parse(encode($data)) === $data` round-trips for mappings, sequences, repeated elements, and scalar casting. Ambiguous strings (e.g. `"true"`, `"123"`, values with leading/trailing whitespace) are automatically quoted on encode so they don't get mis-cast when parsed back.

## Testing

```
composer install
vendor/bin/phpunit -c phpunit-higher.xml   # PHP 7+
vendor/bin/phpunit -c phpunit-lower.xml    # PHP 5.3
```

## License

MIT
