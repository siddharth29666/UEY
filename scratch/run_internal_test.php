<?php

// Set environment for testing
putenv('APP_ENV=testing');
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE=:memory:');
$_ENV['APP_ENV'] = 'testing';
$_ENV['DB_CONNECTION'] = 'sqlite';
$_ENV['DB_DATABASE'] = ':memory:';

require_once __DIR__ . '/../vendor/autoload.php';

$filter = $argv[1] ?? '';
$args = ['phpunit', '--colors=never', '--no-output'];
if ($filter) {
    $args[] = '--filter=' . $filter;
}

$outputFile = __DIR__ . '/test_results.txt';

ob_start();
try {
    $app = new PHPUnit\TextUI\Application();
    $app->run($args);
} catch (\Throwable $e) {
    echo "\nThrowable: " . $e->getMessage() . "\n" . $e->getTraceAsString();
}
$content = ob_get_clean();

file_put_contents($outputFile, $content);
echo "Test execution complete. Output written to scratch/test_results.txt (" . strlen($content) . " bytes)\n";
