<?php

putenv('APP_ENV=testing');
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE=:memory:');
$_ENV['APP_ENV'] = 'testing';
$_ENV['DB_CONNECTION'] = 'sqlite';
$_ENV['DB_DATABASE'] = ':memory:';

require_once __DIR__ . '/../vendor/autoload.php';

use Illuminate\Foundation\Testing\TestCase;

// Bootstrap Laravel
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$testFiles = glob(__DIR__ . '/../tests/Feature/*.php');
$failures = [];
$passedCount = 0;
$failedCount = 0;

foreach ($testFiles as $file) {
    require_once $file;
}

$classes = get_declared_classes();
$testClasses = array_filter($classes, function ($c) {
    return str_starts_with($c, 'Tests\Feature\\') && is_subclass_of($c, TestCase::class);
});

echo "Found " . count($testClasses) . " Feature Test Classes.\n";

$logFile = __DIR__ . '/failures_log.txt';
$fp = fopen($logFile, 'w');

foreach ($testClasses as $testClass) {
    $reflection = new ReflectionClass($testClass);
    $methods = array_filter($reflection->getMethods(), function ($m) {
        return str_starts_with($m->getName(), 'test_');
    });

    foreach ($methods as $method) {
        $testName = $testClass . '::' . $method->getName();
        
        try {
            $instance = new $testClass($method->getName());
            
            // Run test steps manually via PHPUnit Runner
            $suite = new \PHPUnit\Framework\TestSuite();
            $suite->addTest($instance);
            
            $runner = new \PHPUnit\TextUI\TestRunner();
            $result = $runner->run($suite, [
                'colors' => 'never',
                'stderr' => true,
                'stopOnError' => false,
                'stopOnFailure' => false,
            ], [], true);

            if ($result->wasSuccessful()) {
                $passedCount++;
            } else {
                $failedCount++;
                $errStr = "FAILED: $testName\n";
                foreach ($result->failures() as $fail) {
                    $errStr .= "  Failure: " . $fail->thrownException()->getMessage() . "\n";
                    $errStr .= "  Trace: " . substr($fail->thrownException()->getTraceAsString(), 0, 500) . "\n";
                }
                foreach ($result->errors() as $err) {
                    $errStr .= "  Error: " . $err->thrownException()->getMessage() . "\n";
                    $errStr .= "  Trace: " . substr($err->thrownException()->getTraceAsString(), 0, 500) . "\n";
                }
                fwrite($fp, $errStr . "\n-----------------------------------\n");
            }
        } catch (\Throwable $t) {
            $failedCount++;
            fwrite($fp, "THROWABLE: $testName - " . $t->getMessage() . "\n" . $t->getTraceAsString() . "\n\n");
        }
    }
}

fclose($fp);

echo "Passed: $passedCount, Failed: $failedCount\n";
echo "Detailed failure log saved to scratch/failures_log.txt\n";
