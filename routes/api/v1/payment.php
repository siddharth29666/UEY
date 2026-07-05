<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PaymentController;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/payments/history', [PaymentController::class, 'history']);
    Route::get('/payments/{ride}', [PaymentController::class, 'show']);
    Route::get('/payments/invoice/{ride}', [PaymentController::class, 'invoice']);
});
