# IniParser

[![Build Status](https://secure.travis-ci.org/austinhyde/IniParser.png?branch=master)](http://travis-ci.org/austinhyde/IniParser)

IniParser is a simple parser for complex INI files, providing a number of extra syntactic features to the built-in INI parsing functions, including section inheritance, property nesting, and array literals.

**IMPORTANT:** IniParser should be considered beta-quality, and there may still be bugs. Feel free to open an issue or submit a pull request, and I'll take a look at it!

## Installing by [Composer](https://getcomposer.org)

Set your `composer.json` file to have :

```json
{
	"require": {
		"austinhyde/iniparser": "dev-master"
	}
}
```

Then install the dependencies :

```shell
composer install
```

## An Example

Standard INI files look like this:

    key = value
    another_key = another value
    
    [section_name]
    a_sub_key = yet another value

And when parsed with PHP's built-in `parse_ini_string()` or `parse_ini_file()`, looks like

```php
array(
    'key' => 'value',
    'another_key' => 'another value',
    'section_name' => array(
        'a_sub_key' => 'yet another value'
    )
)
```

This is great when you just want a simple configuration file, but here is a super-charged INI file that you might find in the wild:

    environment = testing
    
    [testing]
    debug = true
    database.connection = "mysql:host=127.0.0.1"
    database.name = test
    database.username = 
    database.password =
    secrets = [1,2,3]
    
    [staging : testing]
    database.name = stage
    database.username = staging
    database.password = 12345
    
    [production : staging]
    debug = false;
    database.name = production
    database.username = root

And when parsed with IniParser:

    $parser = new \IniParser('sample.ini');
    $config = $parser->parse();

You get the following structure:

```php
array(
    'environment' => 'testing',
    'testing' => array(
        'debug' => '1',
        'database' => array(
            'connection' => 'mysql:host=127.0.0.1',
            'name' => 'test',
            'username' => '',
            'password' => ''
        ),
        'secrets' => array('1','2','3')
    ),
    'staging' => array(
        'debug' => '1',
        'database' => array(
            'connection' => 'mysql:host=127.0.0.1',
            'name' => 'stage',
            'username' => 'staging',
            'password' => '12345'
        ),
       'secrets' => array('1','2','3')
    ),
    'production' => array(
        'debug' => '',
        'database' => array(
            'connection' => 'mysql:host=127.0.0.1',
            'name' => 'production',
            'username' => 'root',
            'password' => '12345'
        ),
        'secrets' => array('1','2','3')
    )
)
```

## Supported Features

### Array Literals

You can directly create arrays using the syntax `[a, b, c]` on the right hand side of an assignment. For example:

    colors = [blue, green, red]

**NOTE:** At the moment, quoted strings inside array literals have undefined behavior.

### Dictionaries and complex structures

Besides arrays, you can create dictionaries and more complex structures using JSON syntax. For example, you can use:

     people = '{
        "boss": {
           "name": "John", 
           "age": 42 
        }, 
        "staff": [
           {
              "name": "Mark",
              "age": 35 
           }, 
           {
              "name": "Bill", 
              "age": 44 
           }
        ] 
     }'

This turns into an array like:

```php
array(
    'boss' => array(
        'name' => 'John',
        'age' => 42
    ),
    'staff' => array(
        array (
            'name' => 'Mark',
            'age' => 35,
        ),
        array (
            'name' => 'Bill',
            'age' => 44,
        ),
    ),
)
```

**NOTE:**  Remember to wrap the JSON strings in single quotes for a correct analysis. The JSON names must be enclosed in double quotes and trailing commas are not allowed.

### Property Nesting

IniParser allows you to treat properties as associative arrays:

    person.age = 42
    person.name.first = John
    person.name.last = Doe

This turns into an array like:

```php
array (
    'person' => array (
        'age' => 42,
        'name' => array (
            'first' => 'John',
            'last' => 'Doe'
        )
    )
)
```

### Section Inheritance

Keeping to the DRY principle, IniParser allows you to "inherit" from other sections (similar to OOP inheritance), meaning you don't have to continually re-define the same properties over and over again. As you can see in the example above, "production" inherits from "staging", which in turn inherits from "testing".

You can even inherit from multiple parents, as in `[child : p1 : p2 : p3]`. The properties of each parent are merged into the child from left to right, so that the properties in `p1` are overridden by those in `p2`, then by `p3`, then by those in `child` on top of that.

During the inheritance process, if a key ends in a `+`, the merge behavior changes from overwriting the parent value to prepending the parent value (or appending the child value - same thing). So the example file

    [parent]
    arr = [a,b,c]
    val = foo

    [child : parent]
    arr += [x,y,z]
    val += bar

would be parsed into the following:

```php
array(
    'parent' => array(
        'arr' => array('a','b','c'),
        'val' => 'foo'
    ),
    'child' => array(
        'arr' => array('a','b','c','x','y','z'),
        'val' => 'foobar'
    )
)
```

*If you can think of a more useful operation than concatenation for non-array types, please open an issue*

Finally, it is possible to inherit from the special `^` section, representing the top-level or global properties:

    foo = bar

    [sect : ^]

Parses to:

```php
array (
    'foo' => 'bar',
    'sect' => array (
        'foo' => 'bar'
    )
)
```

### ArrayObject

As an added bonus, IniParser also allows you to access the values OO-style:

```php
echo $config->production->database->connection; // output: mysql:host=127.0.0.1
echo $config->staging->debug; // output: 1
```
