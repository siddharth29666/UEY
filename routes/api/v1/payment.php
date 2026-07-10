<?php

use App\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/payments/history', [PaymentController::class, 'history']);
    Route::get('/payments/{ride}', [PaymentController::class, 'show']);
    Route::get('/payments/invoice/{ride}', [PaymentController::class, 'invoice']);
});
