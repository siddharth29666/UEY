<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AdminController;

Route::middleware(['auth:sanctum', 'ability:role:admin'])->prefix('admin')->group(function () {
    Route::get('/drivers', [AdminController::class, 'listDrivers']);
    Route::get('/riders', [AdminController::class, 'listRiders']);
    Route::get('/documents/pending', [AdminController::class, 'pendingDocuments']);
    Route::post('/documents/{document}/verify', [AdminController::class, 'verifyDocument']);
    Route::get('/rides', [AdminController::class, 'activeRides']);
    Route::put('/pricing/{vehicle_type}', [AdminController::class, 'updatePricing']);
    Route::post('/promos', [AdminController::class, 'createPromo']);
});
