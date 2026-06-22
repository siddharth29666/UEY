<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Driver\DriverController;

Route::middleware(['auth:sanctum', 'ability:role:driver'])->prefix('driver')->group(function () {
    // Onboarding & Verification
    Route::post('/onboarding/documents', [DriverController::class, 'uploadDocuments']);
    Route::get('/onboarding/status', [DriverController::class, 'onboardingStatus']);
    
    // Bank Account Management
    Route::get('/bank-account', [DriverController::class, 'getBankAccount']);
    Route::post('/bank-account', [DriverController::class, 'saveBankAccount']);
    
    // Stubs for other actions
    Route::put('/status', [DriverController::class, 'toggleOnlineStatus']);
    Route::post('/location', [DriverController::class, 'updateLocation']);
    Route::put('/settings', [DriverController::class, 'updateSettings']);
    Route::get('/requests', [DriverController::class, 'activeRequests']);
    Route::get('/earnings/summary', [DriverController::class, 'earningsSummary']);
    Route::get('/notifications', [DriverController::class, 'notifications']);
});
