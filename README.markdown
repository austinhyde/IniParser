IniParser is a really simple parser for "complex" INI files.

Standard INI files look like this:

    key = value
    another_key = another value
    
    [section_name]
    a_sub_key = yet another value

And when parsed with PHP's built-in `parse_ini_string()` or `parse_ini_file()`, looks like

    array(
        'key' => 'value',
        'another_key' => 'another value',
        'section_name' => array(
            'a_sub_key' => 'yet another value'
        )
    )

This is great when you just want a super simple configuration file, but here is a super-charged INI file that you might find in the wild:

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

    array(
        'environment' => 'testing',
        'testing' => array(
            'debug' => 'true',
            'database' => array(
                'connection' => 'mysql:host=127.0.0.1',
                'name' => 'test',
                'username' => '',
                'password' => ''
            ),
            'secrets' => array('1','2','3')
        ),
        'staging' => array(
            'debug' => 'true',
            'database' => array(
                'connection' => 'mysql:host=127.0.0.1',
                'name' => 'stage',
                'username' => 'staging',
                'password' => '12345'
            ),
            'secrets' => array('1','2','3')
        ),
        'production' => array(
            'debug' => 'false',
            'database' => array(
                'connection' => 'mysql:host=127.0.0.1',
                'name' => 'production',
                'username' => 'root',
                'password' => '12345'
            ),
            'secrets' => array('1','2','3')
        )
    )

As you can see, IniParser supports section inheritance with `[child : parent]`, property nesting with `a.b.c = d`, and simple arrays with `[a,b,c]`.

Additionally, you can sub-class IniParser and override the `parse_key()` and `parse_value()` methods to customize how it parses keys and values.
