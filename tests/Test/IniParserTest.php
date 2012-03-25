<?php
namespace Test;

use \IniParser;

// FIXME
require_once dirname(dirname(__DIR__)) . '/src/IniParser.php';

class IniParserTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
    }

    /**
     * This is a test-case I wrote because I think there are small bugs
     * in {@link \IniParser}. Just to see if a very basic .ini would be
     * parsed as expected.
     *
     * @return void
     */
    public function testIniParserVsParseIniString()
    {
        $ini = <<<EOF
[helloworld]
hello = world
EOF;
        $parseIniString = parse_ini_string($ini, true);

        $iniParser = new IniParser();
        $config    = $iniParser->process($ini);

        $this->assertSame($config, $parseIniString);
    }
}
