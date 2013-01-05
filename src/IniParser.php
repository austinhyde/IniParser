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
        $inheritance_parsed = $this->parseSections($simple_parsed);
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
     * Parse sections and inheritance.
     * @param  array  $simple_parsed
     * @return array  Parsed sections
     */
    private function parseSections(array $simple_parsed)
    {
        // do an initial pass to gather section names
        $sections = array();
        $globals = array();
        foreach ($simple_parsed as $k=>$v) {
            if (is_array($v)) {
                // $k is a section name
                $sections[$k] = $v;
            } else {
                $globals[$k] = $v;
            }
        }

        // now for each section, see if it uses inheritance
        foreach ($sections as $k=>$v) {
            if (false === strpos($k,':')) {
                continue;
            }

            $sects = array_map('trim',array_reverse(explode(':',$k)));
            $root  = array_pop($sects);
            $arr   = $v;
            foreach ($sects as $s) {
                if ($s === '^') {
                    $arr = array_merge($globals, $arr);
                } elseif (array_key_exists($s, $sections)) {
                    $arr = array_merge($sections[$s],$arr);
                } else {
                    throw new \UnexpectedValueException("IniParser: In file '{$this->file}', section '{$root}': Cannot inherit from unknown section '{$s}'");
                }
            }
            $sections[$root] = $arr;
        }


        return array_merge($globals, $sections);
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
            if (is_array($v)) {
                // this element represents a section; recursively parse the value
                $output[$k] = $this->parseKeys($v);
            } else {
                // value is just a value
                // if the key ends in a +, it means we should append to the previous value, if applicable
                $append = false;
                if (preg_match('/\s*\+\s*$/',$k) > 0) {
                    $k = preg_replace('/\s*\+\s*$/', '', $k);
                    $append = true;
                }

                // transform "a.b.c = x" into $output[a][b][c] = x
                $path = explode('.', $k);

                $current =& $output;
                while (($current_key = array_shift($path))) {
                    if (!array_key_exists($current_key, $current)) {
                        if (!empty($path)) {
                            $current[$current_key] = new \ArrayObject(array(), \ArrayObject::ARRAY_AS_PROPS);
                        } else {
                            $current[$current_key] = null;
                        }
                    }
                    $current =& $current[$current_key];
                }

                $value = $this->parseValue($v);

                if ($append && $current !== null) {
                    if (is_array($value)) {
                        if (!is_array($current)) {
                            throw new \LogicException("Cannot append array to inherited value '{$k}'");
                        }
                        $value = array_merge($current, $value);
                    } else {
                        $value = $current . $value;
                    }
                }

                $current = $value;
            }
        }

        return $output;
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
        // if the value looks like [a,b,c,...], interpret as array
        if (preg_match('/\[\s*.*?(?:\s*,\s*.*?)*\s*\]/',$value) > 0) {
            return explode(',',trim(preg_replace('/\s+/','',$value),'[]'));
        }
        return $value;
    }
}
