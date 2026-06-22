<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Rider\RiderController;
use App\Http\Controllers\Driver\DriverController;
use App\Http\Controllers\ChatController;

Route::middleware('auth:sanctum')->group(function () {
    // Rider Ride Actions
    Route::middleware('ability:role:rider')->prefix('rider/rides')->group(function () {
        Route::post('/', [RiderController::class, 'requestRide']);
        Route::post('/schedule', [RiderController::class, 'scheduleRide']);
        Route::get('/current', [RiderController::class, 'currentRide']);
        Route::post('/{ride}/cancel', [RiderController::class, 'cancelRide']);
        Route::get('/{ride}/driver', [RiderController::class, 'driverDetails']);
        Route::get('/history', [RiderController::class, 'rideHistory']);
        Route::get('/{ride}/receipt', [RiderController::class, 'rideReceipt']);
        Route::post('/{ride}/review', [RiderController::class, 'reviewDriver']);
    });

    // Driver Ride Actions
    Route::middleware('ability:role:driver')->prefix('driver')->group(function () {
        Route::post('/requests/{request}/accept', [DriverController::class, 'acceptRequest']);
        Route::post('/requests/{request}/decline', [DriverController::class, 'declineRequest']);
        Route::post('/rides/{ride}/arrive', [DriverController::class, 'arriveAtPickup']);
        Route::post('/rides/{ride}/start', [DriverController::class, 'startRide']);
        Route::post('/rides/{ride}/complete', [DriverController::class, 'completeRide']);
        Route::get('/rides/history', [DriverController::class, 'rideHistory']);
        Route::post('/rides/{ride}/review', [DriverController::class, 'reviewRider']);
    });

    // Shared Ride Communication & Safety
    Route::prefix('rides/{ride}')->group(function () {
        Route::get('/messages', [ChatController::class, 'getMessages']);
        Route::post('/messages', [ChatController::class, 'sendMessage']);
        Route::post('/call', [ChatController::class, 'initiateCall']);
        Route::post('/emergency', [ChatController::class, 'triggerEmergency']);
    });
});
