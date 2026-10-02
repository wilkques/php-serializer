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

    public function testParseThrowsWhenNoOpeningTagFound()
    {
        $thrown = false;

        try {
            Xml::parse('not xml');
        } catch (\RuntimeException $e) {
            $thrown = true;
        }

        $this->assertTrue($thrown);
    }

    public function testParseThrowsWhenClosingTagIsMissing()
    {
        $thrown = false;

        try {
            Xml::parse('<root><abc>text</');
        } catch (\RuntimeException $e) {
            $thrown = true;
        }

        $this->assertTrue($thrown);
    }

    public function testParseThrowsWhenTrailingTextHasNoClosingTag()
    {
        $thrown = false;

        try {
            Xml::parse('<root>text');
        } catch (\RuntimeException $e) {
            $thrown = true;
        }

        $this->assertTrue($thrown);
    }

    public function testParseThrowsWhenTagHasNoClosingTagAtAll()
    {
        $thrown = false;

        try {
            Xml::parse('<root>');
        } catch (\RuntimeException $e) {
            $thrown = true;
        }

        $this->assertTrue($thrown);
    }

    public function testAttributesOnLeafElement()
    {
        $xml = '<root><user id="1" name="bob">bob@example.com</user></root>';

        $this->assertEquals(
            array('user' => array(
                '@attributes' => array('id' => '1', 'name' => 'bob'),
                '@value' => 'bob@example.com',
            )),
            Xml::parse($xml)
        );
    }

    public function testAttributesOnElementWithChildren()
    {
        $xml = '<root><user id="1"><email>bob@example.com</email></user></root>';

        $this->assertEquals(
            array('user' => array(
                '@attributes' => array('id' => '1'),
                'email' => 'bob@example.com',
            )),
            Xml::parse($xml)
        );
    }

    public function testSelfClosingElementWithAttributesHasEmptyValue()
    {
        $xml = '<root><img src="x.png"/></root>';

        $this->assertEquals(
            array('img' => array(
                '@attributes' => array('src' => 'x.png'),
                '@value' => '',
            )),
            Xml::parse($xml)
        );
    }

    public function testAttributeValuesAreEntityDecoded()
    {
        $xml = '<root><abc label="a &amp; b &quot;c&quot;">x</abc></root>';

        $this->assertEquals(
            array('abc' => array(
                '@attributes' => array('label' => 'a & b "c"'),
                '@value' => 'x',
            )),
            Xml::parse($xml)
        );
    }

    public function testElementWithoutAttributesIsUnaffected()
    {
        $xml = '<root><abc>efg</abc></root>';

        $this->assertEquals(array('abc' => 'efg'), Xml::parse($xml));
    }

    public function testEncodeRoundTripsAttributesOnLeaf()
    {
        $data = array('user' => array(
            '@attributes' => array('id' => '1', 'name' => 'bob'),
            '@value' => 'bob@example.com',
        ));

        $this->assertEquals($data, Xml::parse(Xml::encode($data)));
    }

    public function testEncodeRoundTripsAttributesWithChildren()
    {
        $data = array('user' => array(
            '@attributes' => array('id' => '1'),
            'email' => 'bob@example.com',
        ));

        $this->assertEquals($data, Xml::parse(Xml::encode($data)));
    }

    public function testEncodeRoundTripsSelfClosingWithAttributes()
    {
        $data = array('img' => array(
            '@attributes' => array('src' => 'x.png'),
            '@value' => '',
        ));

        $this->assertEquals($data, Xml::parse(Xml::encode($data)));
    }

    public function testEncodeRoundTripsAttributeEntities()
    {
        $data = array('abc' => array(
            '@attributes' => array('label' => 'a & b "c" \'d\''),
            '@value' => 'x',
        ));

        $this->assertEquals($data, Xml::parse(Xml::encode($data)));
    }

    public function testCdataIsReadAsPlainText()
    {
        $xml = '<root><abc><![CDATA[a & b <c>]]></abc></root>';

        $this->assertEquals(array('abc' => 'a & b <c>'), Xml::parse($xml));
    }

    public function testCdataThrowsWhenUnterminated()
    {
        $thrown = false;

        try {
            Xml::parse('<root><abc><![CDATA[a & b');
        } catch (\RuntimeException $e) {
            $thrown = true;
        }

        $this->assertTrue($thrown);
    }

    public function testCdataPreservesEmbeddedXmlDeclaration()
    {
        $xml = '<root><payload><![CDATA[<?xml version="1.0"?><a>1</a>]]></payload></root>';

        $this->assertEquals(
            array('payload' => '<?xml version="1.0"?><a>1</a>'),
            Xml::parse($xml)
        );
    }

    public function testCdataPreservesEmbeddedComment()
    {
        $xml = '<root><abc><![CDATA[a <!-- b --> c]]></abc></root>';

        $this->assertEquals(array('abc' => 'a <!-- b --> c'), Xml::parse($xml));
    }

    public function testCommentBetweenDeclarationAndRootIsIgnored()
    {
        $xml = '<?xml version="1.0"?><!-- top level comment --><root><abc>x</abc></root>';

        $this->assertEquals(array('abc' => 'x'), Xml::parse($xml));
    }

    public function testProcessingInstructionBetweenSiblingElementsIsIgnored()
    {
        $xml = '<root><?some-instruction foo="bar"?><abc>efg</abc></root>';

        $this->assertEquals(array('abc' => 'efg'), Xml::parse($xml));
    }

    public function testCommentThrowsWhenUnterminated()
    {
        $thrown = false;

        try {
            Xml::parse('<root><abc>x</abc><!-- unterminated');
        } catch (\RuntimeException $e) {
            $thrown = true;
        }

        $this->assertTrue($thrown);
    }

    public function testProcessingInstructionThrowsWhenUnterminated()
    {
        $thrown = false;

        try {
            Xml::parse('<root><abc>x</abc><?unterminated');
        } catch (\RuntimeException $e) {
            $thrown = true;
        }

        $this->assertTrue($thrown);
    }

    public function testBoolAttributeValuesRenderAsTrueFalseText()
    {
        $data = array('tag' => array(
            '@attributes' => array('t' => true, 'f' => false),
            '@value' => 'x',
        ));

        $this->assertEquals(
            '<?xml version="1.0"?>' . "\n" . "<root>\n    <tag t=\"true\" f=\"false\">x</tag>\n</root>\n",
            Xml::encode($data)
        );
    }

    public function testBoolAttributeDoesNotRoundTripToBool()
    {
        $data = array('tag' => array(
            '@attributes' => array('t' => true, 'f' => false),
            '@value' => 'x',
        ));

        $this->assertEquals(
            array('tag' => array(
                '@attributes' => array('t' => 'true', 'f' => 'false'),
                '@value' => 'x',
            )),
            Xml::parse(Xml::encode($data))
        );
    }

    public function testEncodeThrowsWhenValueIsArray()
    {
        $thrown = false;

        try {
            Xml::encode(array('tag' => array(
                '@attributes' => array('a' => '1'),
                '@value' => array('x', 'y'),
            )));
        } catch (\RuntimeException $e) {
            $thrown = true;
        }

        $this->assertTrue($thrown);
    }

    public function testEncodeThrowsForInvalidTagName()
    {
        $thrown = false;

        try {
            Xml::encode(array('123' => 'x'));
        } catch (\RuntimeException $e) {
            $thrown = true;
        }

        $this->assertTrue($thrown);
    }

    public function testEncodeThrowsForInvalidAttributeName()
    {
        $thrown = false;

        try {
            Xml::encode(array('tag' => array(
                '@attributes' => array('bad name' => 'x'),
                '@value' => 'y',
            )));
        } catch (\RuntimeException $e) {
            $thrown = true;
        }

        $this->assertTrue($thrown);
    }

    public function testEncodeAcceptsNameWithHyphenDotColon()
    {
        $this->assertEquals(
            '<?xml version="1.0"?>' . "\n" . "<root>\n    <a-b.c:d>x</a-b.c:d>\n</root>\n",
            Xml::encode(array('a-b.c:d' => 'x'))
        );
    }

    public function testNonStringScalarsDoNotRoundTripType()
    {
        $this->assertEquals(array('abc' => 'true'), Xml::parse(Xml::encode(array('abc' => true))));
    }

    public function testOneElementSequenceCollapsesToScalarOnRoundTrip()
    {
        $data = array('items' => array('item' => array('a')));

        $this->assertEquals(
            array('items' => array('item' => 'a')),
            Xml::parse(Xml::encode($data))
        );
    }
}
