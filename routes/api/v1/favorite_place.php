<?php

use App\Http\Controllers\FavoritePlaceController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/favorite-places/default', [FavoritePlaceController::class, 'defaults']);
    Route::get('/favorite-places', [FavoritePlaceController::class, 'index']);
    Route::post('/favorite-places', [FavoritePlaceController::class, 'store']);
    Route::get('/favorite-places/{id}', [FavoritePlaceController::class, 'show']);
    Route::put('/favorite-places/{id}', [FavoritePlaceController::class, 'update']);
    Route::delete('/favorite-places/{id}', [FavoritePlaceController::class, 'destroy']);
});
