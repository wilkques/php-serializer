<?php

namespace Wilkques\Serializer\Tests\Units\Php\Higher;

use PHPUnit\Framework\TestCase;
use Wilkques\Serializer\Xml;

class XmlTest extends TestCase
{
    public function testFlatElements()
    {
        $xml = '<?xml version="1.0"?><root><abc>efg</abc><hij>klm</hij></root>';

        $this->assertEquals(array('abc' => 'efg', 'hij' => 'klm'), Xml::parse($xml));
    }

    public function testNestedElements()
    {
        $xml = '<root><abc>efg</abc><hij><lmn>opq</lmn></hij></root>';

        $this->assertEquals(
            array('abc' => 'efg', 'hij' => array('lmn' => 'opq')),
            Xml::parse($xml)
        );
    }

    public function testRepeatedSiblingElementsBecomeAList()
    {
        $xml = '<root><items><item>a</item><item>b</item></items></root>';

        $this->assertEquals(
            array('items' => array('item' => array('a', 'b'))),
            Xml::parse($xml)
        );
    }

    public function testRepeatedSiblingMappingElementsBecomeAList()
    {
        $xml = '<root><items><item><name>a</name><value>1</value></item>'
            . '<item><name>b</name><value>2</value></item></items></root>';

        $this->assertEquals(
            array('items' => array('item' => array(
                array('name' => 'a', 'value' => '1'),
                array('name' => 'b', 'value' => '2'),
            ))),
            Xml::parse($xml)
        );
    }

    public function testSelfClosingElementIsEmptyString()
    {
        $xml = '<root><abc/></root>';

        $this->assertEquals(array('abc' => ''), Xml::parse($xml));
    }

    public function testCommentsAreIgnored()
    {
        $xml = '<root><!-- a comment --><abc>efg</abc></root>';

        $this->assertEquals(array('abc' => 'efg'), Xml::parse($xml));
    }

    public function testEntityDecoding()
    {
        $xml = '<root><abc>a &amp; b &lt;c&gt; &quot;d&quot; &apos;e&apos;</abc></root>';

        $this->assertEquals(array('abc' => "a & b <c> \"d\" 'e'"), Xml::parse($xml));
    }

    public function testEncodeFlatElements()
    {
        $expected = '<?xml version="1.0"?>' . "\n"
            . "<root>\n    <abc>efg</abc>\n    <hij>klm</hij>\n</root>\n";

        $this->assertEquals($expected, Xml::encode(array('abc' => 'efg', 'hij' => 'klm')));
    }

    public function testEncodeCustomVersionAndEncoding()
    {
        $expected = '<?xml version="1.1" encoding="UTF-8"?>' . "\n"
            . "<root>\n    <abc>efg</abc>\n</root>\n";

        $this->assertEquals(
            $expected,
            Xml::encode(array('abc' => 'efg'), 'root', '1.1', 'UTF-8')
        );
    }

    public function testEncodeRoundTripsNestedElements()
    {
        $data = array('abc' => 'efg', 'hij' => array('lmn' => 'opq'));

        $this->assertEquals($data, Xml::parse(Xml::encode($data)));
    }

    public function testEncodeRoundTripsRepeatedSiblingElements()
    {
        $data = array('items' => array('item' => array('a', 'b')));

        $this->assertEquals($data, Xml::parse(Xml::encode($data)));
    }

    public function testEncodeSelfClosingForEmptyString()
    {
        $data = array('abc' => '');

        $this->assertEquals($data, Xml::parse(Xml::encode($data)));
    }

    public function testEncodeRoundTripsEntities()
    {
        $data = array('abc' => "a & b <c> \"d\" 'e'");

        $this->assertEquals($data, Xml::parse(Xml::encode($data)));
    }
}
