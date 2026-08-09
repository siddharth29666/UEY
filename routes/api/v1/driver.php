<?php

use App\Http\Controllers\Driver\DriverController;
use App\Http\Controllers\Driver\DriverDocumentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'ability:role:driver'])->prefix('driver')->group(function () {
    // Onboarding & Verification
    Route::post('/onboarding/documents', [DriverController::class, 'uploadDocuments']);
    Route::get('/onboarding/status', [DriverController::class, 'onboardingStatus']);

    // Secure Document Access
    Route::get('/documents/{document}/view', [DriverDocumentController::class, 'view'])->name('driver.documents.view');
    Route::get('/documents/{document}/download', [DriverDocumentController::class, 'download'])->name('driver.documents.download');

    // Bank Account Management
    Route::get('/bank-account', [DriverController::class, 'getBankAccount']);
    Route::post('/bank-account', [DriverController::class, 'saveBankAccount']);

    // Availability & Live Location
    Route::post('/status', [DriverController::class, 'toggleOnlineStatus']);
    Route::post('/location', [DriverController::class, 'updateLocation']);
    Route::get('/dashboard', [DriverController::class, 'dashboard']);
    Route::put('/settings', [DriverController::class, 'updateSettings']);
    Route::get('/requests', [DriverController::class, 'activeRequests']);
    Route::get('/earnings/summary', [DriverController::class, 'earningsSummary']);
    Route::get('/notifications', [DriverController::class, 'notifications']);

    // DRIVER SUBSCRIPTION FEATURE TEMPORARILY DISABLED
    // Re-enable this route group by setting DRIVER_SUBSCRIPTION_ENABLED=true in .env / config.
    if (config('app.driver_subscription_enabled', false)) {
        Route::get('/subscription/plans', [\App\Http\Controllers\Driver\DriverSubscriptionController::class, 'plans']);
        Route::get('/subscription/current', [\App\Http\Controllers\Driver\DriverSubscriptionController::class, 'current']);
        Route::get('/subscription/history', [\App\Http\Controllers\Driver\DriverSubscriptionController::class, 'history']);
        Route::get('/subscription/credits', [\App\Http\Controllers\Driver\DriverSubscriptionController::class, 'credits']);
        Route::post('/subscription/purchase', [\App\Http\Controllers\Driver\DriverSubscriptionController::class, 'purchase']);
    }
});
