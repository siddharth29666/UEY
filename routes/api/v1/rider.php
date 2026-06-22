<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Rider\RiderController;

Route::middleware(['auth:sanctum', 'ability:role:rider'])->prefix('rider')->group(function () {
    Route::get('/dashboard', [RiderController::class, 'dashboard']);
    Route::get('/vehicle-types', [RiderController::class, 'vehicleTypes']);
    Route::post('/fare-estimate', [RiderController::class, 'fareEstimate']);
    Route::get('/payment-methods', [RiderController::class, 'paymentMethods']);
    Route::post('/payment-methods', [RiderController::class, 'addPaymentMethod']);
});
