<?php
namespace Test;

use \IniParser;

/**
 * @author Till Klampaeckel <till@php.net>
 */
class IniParserTest extends \PHPUnit_Framework_TestCase
{
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

    /**
     * This is the example from the README.
     *
     * @return void
     */
    public function testComplex()
    {
        $config = $this->getConfig('fixture03.ini');

        $this->assertArrayHasKey('environment', $config);
        $this->assertEquals('testing', $config['environment']);

        $this->assertArrayHasKey('testing', $config);
        $this->assertArrayHasKey('staging', $config);
        $this->assertArrayHasKey('production', $config);

        $this->assertEquals('', $config['testing']['database']['username']);
        $this->assertEquals('staging', $config['staging']['database']['username']);
        $this->assertEquals('root', $config['production']['database']['username']);

        $this->assertEmpty($config['testing']['database']['password']);
        $this->assertEquals($config['staging']['database']['password'], $config['production']['database']['password']);

        $this->assertEquals('1', $config['testing']['debug']);
        $this->assertEquals('1', $config['staging']['debug']);
        $this->assertEquals('', $config['production']['debug']);
    }

    /**
     * Create a config array (from the given fixture).
     *
     * @param $file
     *
     * @return array
     */
    protected function getConfig($file)
    {
        $parser = new IniParser(BASE_DIR . '/tests/fixtures/' . $file);
        $config = $parser->parse();
        return $config;
    }
}
