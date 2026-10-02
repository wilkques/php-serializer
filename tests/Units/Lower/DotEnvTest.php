<?php

namespace Wilkques\Serializer\Tests\Units\Php\Lower;

use PHPUnit\Framework\TestCase;
use Wilkques\Serializer\DotEnv;

class DotEnvTest extends TestCase
{
    public function testParsesFlatKeyValuePairs()
    {
        $contents = "APP_NAME=MyApp\nAPP_DEBUG=true";

        $this->assertEquals(array('APP_NAME' => 'MyApp', 'APP_DEBUG' => 'true'), DotEnv::parse($contents));
    }

    public function testIgnoresCommentsAndBlankLines()
    {
        $contents = "# comment\n\nAPP_NAME=MyApp\n# another comment\nAPP_URL=https://example.com";

        $this->assertEquals(
            array('APP_NAME' => 'MyApp', 'APP_URL' => 'https://example.com'),
            DotEnv::parse($contents)
        );
    }

    public function testStripsExportPrefix()
    {
        $this->assertEquals(array('APP_NAME' => 'MyApp'), DotEnv::parse('export APP_NAME=MyApp'));
    }

    public function testStripsSurroundingQuotes()
    {
        $contents = "A=\"hello world\"\nB='single value'";

        $this->assertEquals(array('A' => 'hello world', 'B' => 'single value'), DotEnv::parse($contents));
    }

    public function testValuesAreNotTypeCast()
    {
        // Type casting is a read-time concern (see env()), not a parse-time
        // one - parse() always returns raw strings.
        $contents = "A=true\nB=false\nC=null\nD=123";

        $this->assertEquals(
            array('A' => 'true', 'B' => 'false', 'C' => 'null', 'D' => '123'),
            DotEnv::parse($contents)
        );
    }

    public function testEncodeRoundTripsFlatValues()
    {
        $data = array('APP_NAME' => 'MyApp', 'APP_URL' => 'https://example.com');

        $this->assertEquals($data, DotEnv::parse(DotEnv::encode($data)));
    }

    public function testEncodeQuotesLeadingTrailingWhitespace()
    {
        $data = array('A' => ' spaced value ');

        $this->assertEquals($data, DotEnv::parse(DotEnv::encode($data)));
    }

    public function testParseThrowsOnMalformedLine()
    {
        $thrown = false;

        try {
            DotEnv::parse('PLAINWORD');
        } catch (\RuntimeException $e) {
            $thrown = true;
        }

        $this->assertTrue($thrown);
    }
}
