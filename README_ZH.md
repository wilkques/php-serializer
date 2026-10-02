# Serializer for PHP

[![TESTS](https://github.com/wilkques/php-serializer/actions/workflows/github-ci.yml/badge.svg)](https://github.com/wilkques/php-serializer/actions/workflows/github-ci.yml)

[English](README.md) | 繁體中文

YAML、XML、INI、TOML、JSON 以及 .env 的解析／輸出器，可以把簡單的設定檔格式轉成純 PHP 陣列（也能轉回去），並保持與 **PHP 5.3** 相容。

## 需求

- PHP >= 5.3
- `wilkques/php-helper`（用於 `Arrays::isList()`）
- 不需要任何延伸模組——`Yaml`、`Xml`、`Toml` 都是手刻的子集解析器，不是包一層 `ext-yaml`/SimpleXML/DOM/外部 TOML 函式庫。`Ini` 和 `Json` 則是包一層 PHP 原生的 `parse_ini_string()`／`json_decode()`／`json_encode()`，本來就不需要延伸模組。

`Yaml`/`Xml`/`Toml` 支援巢狀的 mapping／元素／table，序列／重複元素／inline array，以及純量型別轉換，但不是完整規格的實作（不支援 anchor、flow style、namespace、array-of-tables、inline table、日期、多行字串，或值後面的行內註解）。`Xml` 另外支援屬性與 CDATA：元素的屬性放在保留的 `@attributes` key 下，若該元素本身是純文字葉節點，文字會放在同層的 `@value` key（例如 `<root><user id="1">bob</user></root>` 解析成 `['user' => ['@attributes' => ['id' => '1'], '@value' => 'bob']]`；沒有屬性的元素行為不變）；`<![CDATA[...]]>` 區塊在 parse 時會當成一般（經過 entity-decode、並且跟一般文字一樣會被 trim 掉前後空白的）文字讀入——註解和 processing instruction（包含 CDATA 區塊裡面的）是在掃描過程中就地跳過，而不是在一開始對整份文件做全域剝除，所以不會不小心把 CDATA 裡面寫的註解/宣告也剝掉；但 `encode()` 不會把資料再包回 CDATA，所以這個方向不是來回轉換。`Xml` 仍不支援 namespace，也不支援混合內容（文字和子元素同時出現在同一個節點時，文字會被捨棄，跟先前行為一致）。`Xml::encode()` 如果遇到不合法的標籤名/屬性名，或是 `@value` 本身是陣列，會丟出 `\RuntimeException`，不會默默寫出不合法的 XML 或字面上的 `Array`。除此之外，`Xml::parse(Xml::encode($data)) === $data` 只對字串／陣列形狀的 `$data` 成立，不是全面保證：傳給 `encode()` 的非字串純量（bool/int/float/null）只是方便起見被轉成字串輸出，parse 回來一定是字串（XML 本身沒有原生的純量型別，跟 `DotEnv` 的立場一致）；只有一個元素的序列也無法還原成 list（會變成純量），這是 XML 格式本身的限制——單一元素跟「只有一個元素的 list」在 XML 語法上長得一模一樣，沒有任何 xml-array 慣例能從標記本身分辨兩者。`Ini` 只支援 INI 本身就有的兩層結構（最上層純量 + 一層 `[section]` 純量），而型別能否正確來回轉換（bool/int/float）取決於 `INI_SCANNER_TYPED` 是否存在（PHP >= 5.6.1）——詳細行為請看類別的 docblock。`DotEnv` 解析 `.env` 風格的 `KEY=value` 文字（註解、`export `、加引號的值）——跟其他幾個不同，它不會對值做型別轉換，解析出來永遠是原始字串，因為型別轉換（true/false/null）屬於讀取時的行為，不是解析時的行為。

`Yaml::parse()`、`Toml::parse()`、`DotEnv::parse()`、`Xml::parse()`、`Ini::parse()` 和 `Json::parse()` 遇到格式錯誤的輸入時都會丟出 `\RuntimeException`（不管是不符合預期語法的非空白、非註解行，或是 `Xml`/`Ini`/`Json` 底層解析器本身拒絕的內容），而不是默默產生一個殘缺或錯誤的結果。`Xml::encode()` 也一樣——遇到無法轉成合法 XML 的輸入會丟例外（見上段）。

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

Xml::parse('<user id="1" name="bob">bob@example.com</user>');
// ['@attributes' => ['id' => '1', 'name' => 'bob'], '@value' => 'bob@example.com']
// （root 標籤名本身不會出現在結果裡，跟上面 <root> 的例子一樣）

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

`encode()` 是這個子集下 `parse()` 的反向操作——`parse(encode($data)) === $data` 對於 mapping、序列、重複元素、純量轉型都能正確來回轉換。容易被誤判的字串（例如 `"true"`、`"123"`、前後有空白的值）在 encode 時會自動加上引號，避免 parse 回來時被誤判型別。`Json` 本身就能原生來回轉換（JSON 沒有容易被誤判型別的純量問題需要處理），只有在 PHP < 5.4（`JSON_PRETTY_PRINT`／`JSON_UNESCAPED_SLASHES`／`JSON_UNESCAPED_UNICODE` 這些常數還不存在的版本）才會略過那幾個 flag。

## 測試

```
composer install
vendor/bin/phpunit -c phpunit-higher.xml   # PHP 7+
vendor/bin/phpunit -c phpunit-lower.xml    # PHP 5.3
```

## 授權

MIT
