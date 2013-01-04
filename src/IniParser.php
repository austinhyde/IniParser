<?php
/**
 * [MIT Licensed](http://www.opensource.org/licenses/mit-license.php)
 * 
 * Implements a parser for INI files that supports
 * * Section inheritance
 * * Property nesting
 * * Simple arrays
 * 
 * Compatible with PHP 5.3.0+
 * 
 * @author Austin Hyde
 * @author Till Klampaeckel <till@php.net>
 */
class IniParser
{
    /**
     * Filename of our .ini file.
     * @var string
     */
    protected $file;

    /**
     * @param string $file
     *
     * @return \IniParser
     */
    public function __construct($file = null)
    {
        if ($file !== null) {
            $this->setFile($file);
        }
    }

    /**
     * Parses an INI file
     *
     * @param string $file
     * @return array
     */
    public function parse($file = null)
    {
        if ($file !== null) {
            $this->setFile($file);
        }
        if (empty($this->file)) {
            throw new \LogicException("Need a file to parse.");
        }
        return $this->process(file_get_contents($this->file));
    }

    /**
     * Parses a string with INI contents
     *
     * @param string $src
     *
     * @return array
     */
    public function process($src)
    {
        $simple_parsed      = parse_ini_string($src, true);
        $inheritance_parsed = array();
        foreach ($simple_parsed as $k=>$v) {
            if (false === strpos($k,':')) {
                $inheritance_parsed[$k] = $v;
                continue;
            }
            $sects = array_map('trim',array_reverse(explode(':',$k)));
            $root  = array_pop($sects);
            $arr   = $v;
            foreach ($sects as $s) {
                $arr = array_merge($inheritance_parsed[$s],$arr);
            }
            $inheritance_parsed[$root] = $arr;
        }
        return $this->parseKeys($inheritance_parsed);
    }

    /**
     * @param string $file
     *
     * @return \IniParser
     * @throws \InvalidArgumentException
     */
    public function setFile($file)
    {
        if (!file_exists($file) || !is_readable($file)) {
            throw new \InvalidArgumentException("The file '{$file}' cannot be opened.");
        }
        $this->file = $file;
        return $this;
    }

    /**
     * @param array $arr
     *
     * @return array
     */
    private function parseKeys(array $arr)
    {
        $output = new \ArrayObject(array(), \ArrayObject::ARRAY_AS_PROPS);
        foreach ($arr as $k=>$v) {
            if (true === is_array($v)) { // is a section
                $output[$k] = $this->parseKeys($v);
                continue;
            }

            // not a section
            $v = $this->parseValue($v);
            if (false === strpos($k,'.')) {
                $output[$k] = $v;
            } else {
                $output = $this->recursiveParseKeys(
                    explode('.', $k),
                    $v,
                    $output
                );
            }
        }

        return $output;
    }

    protected function recursiveParseKeys($keys, $value, $parent)
    {
        if (!$keys) {
            return $value;
        }

        $k = array_shift($keys);
        if (!array_key_exists($k,$parent)) {
            $parent[$k] = new \ArrayObject(array(), \ArrayObject::ARRAY_AS_PROPS);
        }

        $v          = $this->recursiveParseKeys($keys,$value,$parent[$k]);
        $parent[$k] = $v;
        return $parent;
    }

    /**
     * Parses and formats the value in a key-value pair
     *
     * @param string $value
     *
     * @return mixed
     */
    protected function parseValue($value)
    {
        if (preg_match('/\[\s*.*?(?:\s*,\s*.*?)*\s*\]/',$value)) {
            return explode(',',trim(preg_replace('/\s+/','',$value),'[]'));
        }
        return $value;
    }
}
