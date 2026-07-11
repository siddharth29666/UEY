<?php

use App\Http\Controllers\ReferralController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/referrals/apply', [ReferralController::class, 'apply']);
    Route::post('/referrals/invite', [ReferralController::class, 'invite']);
    Route::get('/referrals/code', [ReferralController::class, 'code']);
    Route::get('/referrals/history', [ReferralController::class, 'history']);
    Route::get('/referrals/summary', [ReferralController::class, 'summary']);
    Route::get('/referrals/earnings', [ReferralController::class, 'earnings']);
});
