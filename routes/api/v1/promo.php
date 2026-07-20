<?php

use App\Http\Controllers\Rider\PromoController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'ability:role:rider'])->group(function () {
    Route::get('/promos', [PromoController::class, 'index']);
    Route::get('/promos/history', [PromoController::class, 'history']);
    Route::post('/promos/validate', [PromoController::class, 'validatePromo']);
});
