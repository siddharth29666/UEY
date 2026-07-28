<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $generator = app(\L5Swagger\GeneratorFactory::class)->make('default');
    $generator->generateDocs();
    echo "L5_SWAGGER_GENERATE_SUCCESS\n";
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
