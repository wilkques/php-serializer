<?php

namespace Wilkques\Serializer\Tests\Units\Php\Lower;

use PHPUnit\Framework\TestCase;
use Wilkques\Serializer\Toml;

class TomlTest extends TestCase
{
    public function testFlatKeyValuePairs()
    {
        $toml = "abc = \"efg\"\nhij = \"klm\"";

        $this->assertEquals(array('abc' => 'efg', 'hij' => 'klm'), Toml::parse($toml));
    }

    public function testTableHeader()
    {
        $toml = "abc = \"efg\"\n\n[hij]\nlmn = \"opq\"";

        $this->assertEquals(array('abc' => 'efg', 'hij' => array('lmn' => 'opq')), Toml::parse($toml));
    }

    public function testDottedNestedTableHeader()
    {
        $toml = "[a.b.c]\nabc = \"efg\"";

        $this->assertEquals(array('a' => array('b' => array('c' => array('abc' => 'efg')))), Toml::parse($toml));
    }

    public function testInlineArrayOfScalars()
    {
        $toml = 'tags = ["php", "config", 1, true]';

        $this->assertEquals(array('tags' => array('php', 'config', 1, true)), Toml::parse($toml));
    }

    public function testCommentsAndBlankLinesAreIgnored()
    {
        $toml = "# comment\nabc = \"efg\"\n\n# another\nhij = \"klm\"";

        $this->assertEquals(array('abc' => 'efg', 'hij' => 'klm'), Toml::parse($toml));
    }

    public function testScalarCasting()
    {
        $toml = "a = true\nb = false\nc = 1\nd = 1.5\ne = plain";

        $this->assertEquals(
            array('a' => true, 'b' => false, 'c' => 1, 'd' => 1.5, 'e' => 'plain'),
            Toml::parse($toml)
        );
    }

    public function testEncodeRoundTripsFlatValues()
    {
        $data = array('abc' => 'efg', 'hij' => 'klm');

        $this->assertEquals($data, Toml::parse(Toml::encode($data)));
    }

    public function testEncodeRoundTripsNestedTable()
    {
        $data = array('abc' => 'efg', 'hij' => array('lmn' => 'opq'));

        $this->assertEquals($data, Toml::parse(Toml::encode($data)));
    }

    public function testEncodeRoundTripsInlineArray()
    {
        $data = array('tags' => array('php', 'config', 1, true));

        $this->assertEquals($data, Toml::parse(Toml::encode($data)));
    }

    public function testEncodeRoundTripsEscapedStrings()
    {
        $data = array('a' => 'a "quoted" \\ value');

        $this->assertEquals($data, Toml::parse(Toml::encode($data)));
    }

    public function testEncodeRoundTripsTableContainingInlineArray()
    {
        $data = array('hij' => array('tags' => array('a', 'b'), 'lmn' => 'opq'));

        $this->assertEquals($data, Toml::parse(Toml::encode($data)));
    }

    public function testParseThrowsOnMalformedLine()
    {
        $thrown = false;

        try {
            Toml::parse('plainword');
        } catch (\RuntimeException $e) {
            $thrown = true;
        }

        $this->assertTrue($thrown);
    }
}
