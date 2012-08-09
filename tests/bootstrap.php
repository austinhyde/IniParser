<?php
define('BASE_DIR', dirname(__DIR__));
if (file_exists(BASE_DIR . '/vendor/autoload.php')) {
    require_once BASE_DIR . '/vendor/autoload.php';
} else {
    trigger_error("We should use composer.");
    require_once BASE_DIR . '/src/IniParser.php';
}
