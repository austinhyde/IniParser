<?php

/**
 * [MIT Licensed](http://www.opensource.org/licenses/mit-license.php)
 * Copyright (c) 2013 Austin Hyde
 * 
 * Implements a parser for INI files that supports
 * * Section inheritance
 * * Property nesting
 * * Simple arrays
 * 
 * Compatible with PHP 5.2.0+
 * 
 * @author Austin Hyde
 * @author Till Klampaeckel <till@php.net>
 */
class IniParser {

    /**
     * Filename of our .ini file.
     * @var string
     */
    protected $file;

    /**
     * Enable/disable property nesting feature
     * @var boolean 
     */
    public $property_nesting = true;

    /**
     * Use ArrayObject to allow array work as object (true) or use native arrays (false)
     * @var boolean 
     */
    public $use_array_object = true;

    /**
     * Include original sections (pre-inherit names) on the final output
     * @var boolean
     */
    public $include_original_sections = false;

    /**
     * Disable array literal parsing
     */
    const NO_PARSE = 0;

    /**
     * Parse simple arrays using regex (ex: [a,b,c,...])
     */
    const PARSE_SIMPLE = 1;

    /**
     * Parse array literals using JSON, allowing advanced features like
     * dictionaries, array nesting, etc.
     */
    const PARSE_JSON = 2;

    /**
     * Array literals parse mode
     * @var int 
     */
    public $array_literals_behavior = self::PARSE_SIMPLE;

    /**
     * @param string $file
     *
     * @return IniParser
     */
    public function __construct($file = null) {
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
    public function parse($file = null) {
        if ($file !== null) {
            $this->setFile($file);
        }
        if (empty($this->file)) {
            throw new LogicException("Need a file to parse.");
        }

        $simple_parsed = parse_ini_file($this->file, true);
        $inheritance_parsed = $this->parseSections($simple_parsed);
        return $this->parseKeys($inheritance_parsed);
    }

    /**
     * Parses a string with INI contents
     *
     * @param string $src
     *
     * @return array
     */
    public function process($src) {
        $simple_parsed = parse_ini_string($src, true);
        $inheritance_parsed = $this->parseSections($simple_parsed);
        return $this->parseKeys($inheritance_parsed);
    }

    /**
     * @param string $file
     *
     * @return IniParser
     * @throws InvalidArgumentException
     */
    public function setFile($file) {
        if (!file_exists($file) || !is_readable($file)) {
            throw new InvalidArgumentException("The file '{$file}' cannot be opened.");
        }
        $this->file = $file;
        return $this;
    }

    /**
     * Parse sections and inheritance.
     * @param  array  $simple_parsed
     * @return array  Parsed sections
     */
    private function parseSections(array $simple_parsed) {
        // do an initial pass to gather section names
        $sections = array();
        $globals = array();
        foreach ($simple_parsed as $k => $v) {
            if (is_array($v)) {
                // $k is a section name
                $sections[$k] = $v;
            } else {
                $globals[$k] = $v;
            }
        }

        // now for each section, see if it uses inheritance
        $output_sections = array();
        foreach ($sections as $k => $v) {
            $sects = array_map('trim', array_reverse(explode(':', $k)));
            $root = array_pop($sects);
            $arr = $v;
            foreach ($sects as $s) {
                if ($s === '^') {
                    $arr = array_merge($globals, $arr);
                } elseif (array_key_exists($s, $output_sections)) {
                    $arr = array_merge($output_sections[$s], $arr);
                } elseif (array_key_exists($s, $sections)) {
                    $arr = array_merge($sections[$s], $arr);
                } else {
                    throw new UnexpectedValueException("IniParser: In file '{$this->file}', section '{$root}': Cannot inherit from unknown section '{$s}'");
                }
            }

            if ($this->include_original_sections) {
                $output_sections[$k] = $v;
            }
            $output_sections[$root] = $arr;
        }


        return $globals + $output_sections;
    }

    /**
     * @param array $arr
     *
     * @return array
     */
    private function parseKeys(array $arr) {
        $output = $this->getArrayValue();
        $append_regex = '/\s*\+\s*$/';
        foreach ($arr as $k => $v) {
            if (is_array($v) && FALSE === strpos($k, '.')) {
                // this element represents a section; recursively parse the value
                $output[$k] = $this->parseKeys($v);
            } else {
                // if the key ends in a +, it means we should append to the previous value, if applicable
                $append = false;
                if (preg_match($append_regex, $k)) {
                    $k = preg_replace($append_regex, '', $k);
                    $append = true;
                }

                // transform "a.b.c = x" into $output[a][b][c] = x
                $current = & $output;

                $path = $this->property_nesting ? explode('.', $k) : array($k);
                while (($current_key = array_shift($path)) !== null) {
                    if ('string' === gettype($current)) {
                        $current = array($current);
                    }

                    if (!array_key_exists($current_key, $current)) {
                        if (!empty($path)) {
                            $current[$current_key] = $this->getArrayValue();
                        } else {
                            $current[$current_key] = null;
                        }
                    }
                    $current = & $current[$current_key];
                }

                // parse value
                $value = $v;
                if (!is_array($v)) {
                  $value = $this->parseValue($v);
                }

                if ($append && $current !== null) {
                    if (is_array($value)) {
                        if (!is_array($current)) {
                            throw new LogicException("Cannot append array to inherited value '{$k}'");
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
    protected function parseValue($value) {
        switch ($this->array_literals_behavior) {
            case self::PARSE_JSON:
                if (in_array(substr($value, 0, 1), array('[', '{')) && in_array(substr($value, -1), array(']', '}'))) {
                    if (defined('JSON_BIGINT_AS_STRING')) {
                        $output = json_decode($value, true, 512, JSON_BIGINT_AS_STRING);
                    } else {
                        $output = json_decode($value, true);
                    }

                    if ($output !== NULL) {
                        return $output;
                    }
                }
            // fallthrough
            // try regex parser for simple estructures not JSON-compatible (ex: colors = [blue, green, red])
            case self::PARSE_SIMPLE:
                // if the value looks like [a,b,c,...], interpret as array
                if (preg_match('/^\[\s*.*?(?:\s*,\s*.*?)*\s*\]$/', trim($value))) {
                    return array_map('trim', explode(',', trim(trim($value), '[]')));
                }
                break;
        }
        return $value;
    }

    protected function getArrayValue($array = array()) {
        if ($this->use_array_object) {
            return new ArrayObject($array, ArrayObject::ARRAY_AS_PROPS);
        } else {
            return $array;
        }
    }

}
