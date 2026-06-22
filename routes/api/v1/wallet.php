<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Rider\RiderController;
use App\Http\Controllers\Driver\DriverController;

Route::middleware('auth:sanctum')->prefix('wallet')->group(function () {
    // Rider Wallet
    Route::middleware('ability:role:rider')->group(function () {
        Route::get('/rider', [RiderController::class, 'getWallet']);
        Route::post('/rider/topup', [RiderController::class, 'walletTopup']);
    });

    // Driver Wallet
    Route::middleware('ability:role:driver')->group(function () {
        Route::post('/driver/cashout', [DriverController::class, 'walletCashout']);
    });
});
