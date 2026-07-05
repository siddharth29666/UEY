<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ReviewController;

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/rides/{ride}/review', [ReviewController::class, 'store']);
    Route::get('/rides/{ride}/review', [ReviewController::class, 'show']);
    Route::get('/drivers/{driver}/reviews', [ReviewController::class, 'driverReviews']);
    Route::get('/riders/{rider}/reviews', [ReviewController::class, 'riderReviews']);
});
