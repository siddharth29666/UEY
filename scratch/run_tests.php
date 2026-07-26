<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

// Run phpunit command and capture output
$outputFile = __DIR__ . '/test_output.txt';

putenv('APP_ENV=testing');
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE=:memory:');

exec('..\vendor\bin\phpunit --colors=never > ' . escapeshellarg($outputFile) . ' 2>&1', $output, $returnCode);

echo "Exit Code: " . $returnCode . "\n";
echo "Output saved to " . $outputFile . "\n";
