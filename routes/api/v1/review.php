<?php

use App\Http\Controllers\ReviewController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/rides/{ride}/review', [ReviewController::class, 'store']);
    Route::get('/rides/{ride}/review', [ReviewController::class, 'show']);
    Route::get('/drivers/{driver}/reviews', [ReviewController::class, 'driverReviews']);
    Route::get('/riders/{rider}/reviews', [ReviewController::class, 'riderReviews']);
});
