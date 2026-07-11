<?php

use App\Http\Controllers\EmergencyController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    // Emergency Contacts
    Route::get('/emergency-contacts', [EmergencyController::class, 'contacts']);
    Route::get('/emergency-contacts/default', [EmergencyController::class, 'defaultContacts']);
    Route::post('/emergency-contacts', [EmergencyController::class, 'storeContact']);
    Route::put('/emergency-contacts/{id}', [EmergencyController::class, 'updateContact']);
    Route::delete('/emergency-contacts/{id}', [EmergencyController::class, 'destroyContact']);

    // SOS Alerts
    Route::post('/rides/{ride}/sos', [EmergencyController::class, 'trigger']);
    Route::post('/emergency-alerts/{id}/acknowledge', [EmergencyController::class, 'acknowledge']);
    Route::post('/emergency-alerts/{id}/resolve', [EmergencyController::class, 'resolve']);
    Route::get('/emergency-alerts', [EmergencyController::class, 'indexAlerts']);
    Route::get('/emergency-alerts/{id}', [EmergencyController::class, 'showAlert']);

    // Admin SOS Moderation
    Route::middleware('ability:role:admin')->prefix('admin')->group(function () {
        Route::get('/emergency-alerts/statistics', [EmergencyController::class, 'adminStatistics']);
        Route::get('/emergency-alerts', [EmergencyController::class, 'adminIndex']);
        Route::get('/emergency-alerts/{id}', [EmergencyController::class, 'adminShow']);
        Route::post('/emergency-alerts/{id}/resolve', [EmergencyController::class, 'adminResolve']);
        Route::post('/emergency-alerts/{id}/assign', [EmergencyController::class, 'adminAssign']);
        Route::post('/emergency-alerts/{id}/close', [EmergencyController::class, 'adminClose']);
    });
});
