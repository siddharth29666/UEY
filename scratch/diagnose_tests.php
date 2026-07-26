<?php

putenv('APP_ENV=testing');
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE=:memory:');
$_ENV['APP_ENV'] = 'testing';
$_ENV['DB_CONNECTION'] = 'sqlite';
$_ENV['DB_DATABASE'] = ':memory:';

require_dir_recursive(__DIR__ . '/../tests');

// We can bootstrap Laravel and run individual test classes using Reflection / PHPUnit
require __DIR__ . '/../vendor/autoload.php';

function require_dir_recursive($dir) {
    foreach (glob($dir . '/*') as $file) {
        if (is_dir($file)) {
            require_dir_recursive($file);
        } elseif (substr($file, -4) === '.php') {
            require_once $file;
        }
    }
}
