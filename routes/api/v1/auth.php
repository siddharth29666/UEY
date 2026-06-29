<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\SendOtpController;
use App\Http\Controllers\Auth\VerifyOtpController;
use App\Http\Controllers\Auth\RegisterRiderController;
use App\Http\Controllers\Auth\RegisterDriverController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\ProfileController;
use App\Http\Controllers\Auth\RefreshTokenController;
use App\Http\Controllers\Auth\PasswordResetController;

Route::middleware('throttle:auth')->group(function () {
    Route::post('/register/rider', RegisterRiderController::class);
    Route::post('/register/driver', RegisterDriverController::class);
    Route::post('/login', LoginController::class);
    Route::post('/otp/send', SendOtpController::class);
    Route::post('/otp/verify', VerifyOtpController::class);
    Route::post('/auth/forgot-password', [PasswordResetController::class, 'forgotPassword']);
    Route::post('/auth/reset-password', [PasswordResetController::class, 'resetPassword']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', LogoutController::class);
    Route::post('/token/refresh', RefreshTokenController::class);
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::delete('/profile/delete-account', [ProfileController::class, 'deleteAccount']);
});
