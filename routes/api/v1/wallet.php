<?php

use App\Http\Controllers\WalletController;
use Illuminate\Support\Facades\Route;

// Public Stripe webhook
Route::post('/stripe/webhook', [WalletController::class, 'stripeWebhook']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/wallet', [WalletController::class, 'show']);
    Route::get('/wallet/transactions', [WalletController::class, 'transactions']);
    Route::post('/wallet/top-up', [WalletController::class, 'topUp']);
    Route::post('/wallet/withdraw', [WalletController::class, 'withdraw']);
    Route::get('/wallet/withdrawals', [WalletController::class, 'withdrawals']);
    Route::get('/wallet/withdrawals/{id}', [WalletController::class, 'showWithdrawal']);
});
