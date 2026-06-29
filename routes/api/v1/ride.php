<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Rider\RiderController;
use App\Http\Controllers\Driver\DriverController;
use App\Http\Controllers\ChatController;

Route::middleware('auth:sanctum')->group(function () {
    // Phase 5 Rider Ride Actions
    Route::middleware('ability:role:rider')->prefix('rides')->group(function () {
        Route::post('/estimate', [RiderController::class, 'estimateRide']);
        Route::post('/request', [RiderController::class, 'requestRide']);
        Route::get('/active', [RiderController::class, 'activeRide']);
        Route::post('/{ride}/cancel', [RiderController::class, 'cancelRide']);
        Route::get('/{ride}', [RiderController::class, 'showRide']);
        Route::get('/', [RiderController::class, 'rideHistory']);
    });

    // Phase 5 Driver Ride Actions
    Route::middleware('ability:role:driver')->prefix('driver')->group(function () {
        Route::get('/ride-requests', [DriverController::class, 'rideRequests']);
        Route::post('/ride-requests/{request}/accept', [DriverController::class, 'acceptRideRequest']);
        Route::post('/ride-requests/{request}/decline', [DriverController::class, 'declineRideRequest']);
        Route::get('/active-ride', [DriverController::class, 'activeRide']);
    });

    // Phase 6 Driver Ride Lifecycle Execution Actions
    Route::middleware('ability:role:driver')->prefix('driver/rides/{ride}')->group(function () {
        Route::get('/', [DriverController::class, 'showRide']);
        Route::post('/arriving', [DriverController::class, 'markArriving']);
        Route::post('/arrived', [DriverController::class, 'markArrived']);
        Route::post('/start', [DriverController::class, 'startRide']);
        Route::post('/complete', [DriverController::class, 'completeRide']);
    });

    // Rider Ride Actions (Legacy Stubs)
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

    // Driver Ride Actions (Legacy Stubs)
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
