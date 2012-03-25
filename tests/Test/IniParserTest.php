<?php
namespace Test;

use \IniParser;

define('BASE_DIR', dirname(dirname(__DIR__)));

// FIXME
require_once BASE_DIR . '/src/IniParser.php';

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

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testConfigNotFound()
    {
        new IniParser('/this/should/never/exist.ini');
    }

    /**
     * A slightly more complex test to confirm parsing works.
     *
     * @return void
     */
    public function testParser()
    {
        $config = $this->getConfig('fixture01.ini');

        $this->assertArrayHasKey('production', $config);

        $productionConfig = $config['production'];

        $this->assertArrayHasKey('hello', $productionConfig);
        $this->assertArrayHasKey('super', $productionConfig);

        $super = $productionConfig['super'];

        $this->assertArrayHasKey('funny', $super);
        $this->assertEquals('config', $super['funny']);
    }

    /**
     * Confirm that the 'dev' environment inherits all values from the 'prod' environment.
     *
     * @return void
     */
    public function testInheritance()
    {
        $config = $this->getConfig('fixture02.ini');

        $this->assertArrayHasKey('prod', $config);
        $this->assertArrayHasKey('dev', $config);

        $this->assertSame($config['prod'], $config['dev']);
    }
}
