<?php

namespace Wilkques\Serializer\Tests\Units\Php\Lower;

use PHPUnit\Framework\TestCase;
use Wilkques\Serializer\Json;

class JsonTest extends TestCase
{
    public function testParsesFlatObject()
    {
        $this->assertEquals(array('abc' => 'efg', 'hij' => 'klm'), Json::parse('{"abc":"efg","hij":"klm"}'));
    }

    public function testParsesNestedObject()
    {
        $this->assertEquals(
            array('abc' => 'efg', 'hij' => array('lmn' => 'opq')),
            Json::parse('{"abc":"efg","hij":{"lmn":"opq"}}')
        );
    }

    public function testParsesScalarTypes()
    {
        $json = '{"a":true,"b":false,"c":null,"d":1,"e":1.5,"f":"plain"}';

        $this->assertEquals(
            array('a' => true, 'b' => false, 'c' => null, 'd' => 1, 'e' => 1.5, 'f' => 'plain'),
            Json::parse($json)
        );
    }

    public function testParsesArray()
    {
        $this->assertEquals(array('php', 'config'), Json::parse('["php","config"]'));
    }

    public function testParseThrowsOnMalformedJson()
    {
        // Plain try/catch (not expectException()/setExpectedException())
        // so this test runs unchanged on every PHPUnit version this
        // package's CI matrix resolves, from PHPUnit 4.8 (PHP 5.3) up.
        $thrown = false;

        try {
            Json::parse('{"abc":');
        } catch (\RuntimeException $e) {
            $thrown = true;
        }

        $this->assertTrue($thrown);
    }

    public function testEncodeFlatObject()
    {
        $this->assertEquals(
            array('abc' => 'efg', 'hij' => 'klm'),
            Json::parse(Json::encode(array('abc' => 'efg', 'hij' => 'klm')))
        );
    }

    public function testEncodeRoundTripsNestedObject()
    {
        $data = array('abc' => 'efg', 'hij' => array('lmn' => 'opq'));

        $this->assertEquals($data, Json::parse(Json::encode($data)));
    }

    public function testEncodeRoundTripsScalarTypes()
    {
        $data = array('a' => true, 'b' => false, 'c' => null, 'd' => 1, 'e' => 1.5, 'f' => 'plain');

        $this->assertEquals($data, Json::parse(Json::encode($data)));
    }

    public function testEncodeRoundTripsList()
    {
        $data = array('php', 'config');

        $this->assertEquals($data, Json::parse(Json::encode($data)));
    }

    public function testEncodeDoesNotEscapeSlashes()
    {
        if (!defined('JSON_UNESCAPED_SLASHES')) {
            $this->markTestSkipped('JSON_UNESCAPED_SLASHES requires PHP >= 5.4');
        }

        $this->assertSame(false, strpos(Json::encode(array('url' => 'https://example.com')), '\\/'));
    }
}
