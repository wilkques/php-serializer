<?php

namespace Wilkques\Serializer\Tests\Units\Php\Higher;

use PHPUnit\Framework\TestCase;
use Wilkques\Serializer\Yaml;

class YamlTest extends TestCase
{
    public function testFlatMapping()
    {
        $result = Yaml::parse("abc: efg\nhij: klm");

        $this->assertEquals(array('abc' => 'efg', 'hij' => 'klm'), $result);
    }

    public function testNestedMapping()
    {
        $result = Yaml::parse("abc: efg\nhij:\n  lmn: opq");

        $this->assertEquals(
            array('abc' => 'efg', 'hij' => array('lmn' => 'opq')),
            $result
        );
    }

    public function testSequenceOfScalars()
    {
        $result = Yaml::parse("tags:\n  - php\n  - config");

        $this->assertEquals(array('tags' => array('php', 'config')), $result);
    }

    public function testSequenceOfMappings()
    {
        $yaml = "items:\n  - name: a\n    value: 1\n  - name: b\n    value: 2";

        $result = Yaml::parse($yaml);

        $this->assertEquals(
            array('items' => array(
                array('name' => 'a', 'value' => 1),
                array('name' => 'b', 'value' => 2),
            )),
            $result
        );
    }

    public function testCommentsAndBlankLinesAreIgnored()
    {
        $yaml = "# a comment\nabc: efg\n\n# another comment\nhij: klm";

        $this->assertEquals(array('abc' => 'efg', 'hij' => 'klm'), Yaml::parse($yaml));
    }

    public function testScalarCasting()
    {
        $yaml = "a: true\nb: false\nc: null\nd: ~\ne: 1\nf: 1.5\ng: \"quoted\"\nh: 'single'\ni: plain";

        $this->assertEquals(
            array(
                'a' => true,
                'b' => false,
                'c' => null,
                'd' => null,
                'e' => 1,
                'f' => 1.5,
                'g' => 'quoted',
                'h' => 'single',
                'i' => 'plain',
            ),
            Yaml::parse($yaml)
        );
    }

    public function testEmptyMappingValueIsNull()
    {
        $this->assertEquals(array('abc' => null), Yaml::parse('abc:'));
    }

    public function testEncodeFlatMapping()
    {
        $this->assertEquals("abc: efg\nhij: klm\n", Yaml::encode(array('abc' => 'efg', 'hij' => 'klm')));
    }

    public function testEncodeRoundTripsNestedMapping()
    {
        $data = array('abc' => 'efg', 'hij' => array('lmn' => 'opq'));

        $this->assertEquals($data, Yaml::parse(Yaml::encode($data)));
    }

    public function testEncodeRoundTripsSequenceOfScalars()
    {
        $data = array('tags' => array('php', 'config'));

        $this->assertEquals($data, Yaml::parse(Yaml::encode($data)));
    }

    public function testEncodeRoundTripsSequenceOfMappings()
    {
        $data = array('items' => array(
            array('name' => 'a', 'value' => 1),
            array('name' => 'b', 'value' => 2),
        ));

        $this->assertEquals($data, Yaml::parse(Yaml::encode($data)));
    }

    public function testEncodeRoundTripsScalarCasting()
    {
        $data = array('a' => true, 'b' => false, 'c' => null, 'd' => 1, 'e' => 1.5, 'f' => 'plain');

        $this->assertEquals($data, Yaml::parse(Yaml::encode($data)));
    }

    public function testEncodeQuotesAmbiguousStrings()
    {
        $data = array('a' => 'true', 'b' => '123', 'c' => '', 'd' => ' spaced ');

        $this->assertEquals($data, Yaml::parse(Yaml::encode($data)));
    }
}
