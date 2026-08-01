<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Artisan;

ob_start();
$status = Artisan::call('test', [
    '--filter' => 'AdminReportsTest|DriverVerificationTest|AdminCmsAndSettingsTest',
]);
$output = Artisan::output();
ob_end_clean();

file_put_contents(__DIR__ . '/test_results.log', $output . "\nSTATUS: " . $status);
