<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - UEY Premium Mobility
|--------------------------------------------------------------------------
|
| Versioned route files are grouped and loaded dynamically below.
|
*/

Route::prefix('v1')->group(function () {
    require __DIR__ . '/api/v1/auth.php';
    require __DIR__ . '/api/v1/rider.php';
    require __DIR__ . '/api/v1/driver.php';
    require __DIR__ . '/api/v1/wallet.php';
    require __DIR__ . '/api/v1/ride.php';
    require __DIR__ . '/api/v1/notification.php';
    require __DIR__ . '/api/v1/admin.php';
});
