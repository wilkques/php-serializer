# Serializer for PHP

[English](README.md) | 繁體中文

YAML、XML、INI、TOML 以及 .env 的解析／輸出器，可以把簡單的設定檔格式轉成純 PHP 陣列（也能轉回去），並保持與 **PHP 5.3** 相容。

## 需求

- PHP >= 5.3
- `wilkques/php-helper`（用於 `Arrays::isList()`）
- 不需要任何延伸模組——`Yaml`、`Xml`、`Toml` 都是手刻的子集解析器，不是包一層 `ext-yaml`/SimpleXML/DOM/外部 TOML 函式庫。`Ini` 則是包一層 PHP 原生的 `parse_ini_string()`，本來就不需要延伸模組。

`Yaml`/`Xml`/`Toml` 支援巢狀的 mapping／元素／table，序列／重複元素／inline array，以及純量型別轉換，但不是完整規格的實作（不支援 anchor、flow style、attribute、CDATA、namespace、array-of-tables、inline table、日期，或多行字串）。`Ini` 只支援 INI 本身就有的兩層結構（最上層純量 + 一層 `[section]` 純量），而型別能否正確來回轉換（bool/int/float）取決於 `INI_SCANNER_TYPED` 是否存在（PHP >= 5.6.1）——詳細行為請看類別的 docblock。`DotEnv` 解析 `.env` 風格的 `KEY=value` 文字（註解、`export `、加引號的值）——跟其他幾個不同，它不會對值做型別轉換，解析出來永遠是原始字串，因為型別轉換（true/false/null）屬於讀取時的行為，不是解析時的行為。

## 安裝

```
composer require wilkques/serializer
```

## 使用方式

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

`encode()` 是這個子集下 `parse()` 的反向操作——`parse(encode($data)) === $data` 對於 mapping、序列、重複元素、純量轉型都能正確來回轉換。容易被誤判的字串（例如 `"true"`、`"123"`、前後有空白的值）在 encode 時會自動加上引號，避免 parse 回來時被誤判型別。

## 測試

```
composer install
vendor/bin/phpunit -c phpunit-higher.xml   # PHP 7+
vendor/bin/phpunit -c phpunit-lower.xml    # PHP 5.3
```

## 授權

MIT
