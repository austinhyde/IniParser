<?php
/**
 * [MIT Licensed](http://www.opensource.org/licenses/mit-license.php)
 * 
 * Implements a parser for INI files that supports
 * * Section inheritance
 * * Property nesting
 * * Simple arrays
 * 
 * Compatible with PHP 5.2.3 and up
 * 
 * @author Austin Hyde
 */

class IniParser
{
    public function __construct()
    {

    }

    /**
     * Parses an INI file
     *
     * @param string $file
     * @return array
     */
    public function parse($file)
    {
        return $this->process(file_get_contents($file));
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
            $root = array_pop($sects);
            $arr = $v;
            foreach ($sects as $s) {
                $arr = array_merge($inheritance_parsed[$s],$arr);
            }
            $inheritance_parsed[$root] = $arr;
        }
        return $this->parse_keys($inheritance_parsed);
    }

    /**
     * @param array $arr
     *
     * @return array
     */
    private function parse_keys(array $arr)
    {
        $output = array();
        foreach ($arr as $k=>$v) {
            if (is_array($v)) { // is a section
                $output[$k] = $this->parse_keys($v);
                continue;
            }

            // not a section
            $v = $this->parse_value($v);
            if (strpos($k,'.')===false) {
                $output[$k] = $v;
            } else {
                $output = $this->rec_keys(explode('.',$k),$v,$output);
            }
        }

        return $output;
    }

    protected function rec_keys($keys,$value,$parent)
    {
        if (!$keys) {
            return $value;
        }

        $k = $this->parse_value(array_shift($keys));
        if (!array_key_exists($k,$parent)) {
            $parent[$k] = array();
        }

        $v          = $this->rec_keys($keys,$value,$parent[$k]);
        $parent[$k] = $v;
        return $parent;
    }

    /**
     * Parses and formats the key in a key-value pair
     *
     * @param string $key
     * @return string
     */
    protected function parse_key($key)
    {
        return $key;
    }

    /**
     * Parses and formats the value in a key-value pair
     *
     * @param string $value
     * @return void
     */
    protected function parse_value($value)
    {
        if (preg_match('/\[\s*.*?(?:\s*,\s*.*?)*\s*\]/',$v)) {
            return explode(',',trim(preg_replace('/\s+/','',$v),'[]'));
        }
    }
}