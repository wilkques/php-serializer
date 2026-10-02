<?php

namespace Wilkques\Serializer\Tests\Units\Php\Lower;

use PHPUnit\Framework\TestCase;
use Wilkques\Serializer\Ini;

class IniTest extends TestCase
{
    public function testFlatValues()
    {
        $ini = "abc = \"efg\"\nhij = \"klm\"";

        $this->assertEquals(array('abc' => 'efg', 'hij' => 'klm'), Ini::parse($ini));
    }

    public function testSections()
    {
        $ini = "abc = \"efg\"\n\n[hij]\nlmn = \"opq\"";

        $this->assertEquals(array('abc' => 'efg', 'hij' => array('lmn' => 'opq')), Ini::parse($ini));
    }

    public function testEncodeRoundTripsFlatValues()
    {
        $data = array('abc' => 'efg', 'hij' => 'klm');

        $this->assertEquals($data, Ini::parse(Ini::encode($data)));
    }

    public function testEncodeRoundTripsSections()
    {
        $data = array('abc' => 'efg', 'hij' => array('lmn' => 'opq'));

        $this->assertEquals($data, Ini::parse(Ini::encode($data)));
    }

    public function testEncodeRoundTripsScalarCasting()
    {
        // Type round-tripping depends on INI_SCANNER_TYPED (PHP >= 5.6.1);
        // without it the native scanner returns everything as strings.
        $data = array('a' => true, 'b' => false, 'd' => 1, 'e' => 1.5);

        $expected = defined('INI_SCANNER_TYPED')
            ? $data
            : array('a' => '1', 'b' => '', 'd' => '1', 'e' => '1.5');

        $this->assertEquals($expected, Ini::parse(Ini::encode($data)));
    }

    public function testEncodeNullBecomesEmptyStringOnDecode()
    {
        // PHP's own INI scanner has no NULL representation for an empty
        // value, even under INI_SCANNER_TYPED - this is a native limitation,
        // not something this class can work around.
        $this->assertEquals(array('a' => ''), Ini::parse(Ini::encode(array('a' => null))));
    }

    public function testEncodeQuotesAmbiguousStrings()
    {
        $data = array('a' => 'true', 'b' => '123', 'c' => 'plain');

        $this->assertEquals($data, Ini::parse(Ini::encode($data)));
    }
}
