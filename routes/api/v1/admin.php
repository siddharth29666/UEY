<?php

use App\Http\Controllers\Admin\AdminAuditLogController;
use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminDriverController;
use App\Http\Controllers\Admin\AdminNotificationController;
use App\Http\Controllers\Admin\AdminPromoCodeController;
use App\Http\Controllers\Admin\AdminReviewController;
use App\Http\Controllers\Admin\AdminRideController;
use App\Http\Controllers\Admin\AdminRiderController;
use App\Http\Controllers\Admin\AdminSystemSettingsController;
use App\Http\Controllers\Admin\AdminWalletController;
use App\Http\Controllers\Admin\AdminWithdrawalController;
use Illuminate\Support\Facades\Route;

// 1. Admin login does not require auth:sanctum
Route::post('/admin/login', [AdminAuthController::class, 'login']);

// 2. Protected admin routes
Route::middleware(['auth:sanctum', 'ability:role:admin'])->prefix('admin')->group(function () {

    // Auth & Profile
    Route::post('/logout', [AdminAuthController::class, 'logout']);
    Route::get('/profile', [AdminAuthController::class, 'profile']);
    Route::put('/profile', [AdminAuthController::class, 'updateProfile']);
    Route::put('/change-password', [AdminAuthController::class, 'changePassword']);

    // Dashboard Analytics
    Route::get('/dashboard', [AdminDashboardController::class, 'index']);

    // Rider Management
    Route::get('/riders', [AdminRiderController::class, 'index']);
    Route::get('/riders/{id}', [AdminRiderController::class, 'show']);
    Route::put('/riders/{id}', [AdminRiderController::class, 'update']);
    Route::post('/riders/{id}/block', [AdminRiderController::class, 'block']);
    Route::post('/riders/{id}/unblock', [AdminRiderController::class, 'unblock']);
    Route::delete('/riders/{id}', [AdminRiderController::class, 'destroy']);

    // Driver Onboarding & Verification (Existing controller methods)
    Route::get('/documents/pending', [AdminController::class, 'pendingDocuments']);
    Route::post('/documents/{document}/verify', [AdminController::class, 'verifyDocument']);

    // Driver Management
    Route::get('/drivers', [AdminDriverController::class, 'index']);
    Route::get('/drivers/{id}', [AdminDriverController::class, 'show']);
    Route::put('/drivers/{id}', [AdminDriverController::class, 'update']);
    Route::post('/drivers/{id}/approve', [AdminDriverController::class, 'approve']);
    Route::post('/drivers/{id}/reject', [AdminDriverController::class, 'reject']);
    Route::post('/drivers/{id}/block', [AdminDriverController::class, 'block']);
    Route::post('/drivers/{id}/unblock', [AdminDriverController::class, 'unblock']);
    Route::get('/drivers/{id}/documents', [AdminDriverController::class, 'documents']);

    // Ride Management
    Route::get('/rides', [AdminRideController::class, 'index']);
    Route::get('/rides/{id}', [AdminRideController::class, 'show']);
    Route::post('/rides/{id}/cancel', [AdminRideController::class, 'cancel']);
    Route::post('/rides/{id}/refund', [AdminRideController::class, 'refund']);
    Route::get('/rides/{id}/timeline', [AdminRideController::class, 'timeline']);

    // Wallet Administration
    Route::get('/wallets', [AdminWalletController::class, 'index']);
    Route::get('/wallets/{id}', [AdminWalletController::class, 'show']);
    Route::post('/wallets/{id}/credit', [AdminWalletController::class, 'credit']);
    Route::post('/wallets/{id}/debit', [AdminWalletController::class, 'debit']);
    Route::get('/wallet-transactions', [AdminWalletController::class, 'transactions']);

    // Withdrawal Management
    Route::get('/withdrawals', [AdminWithdrawalController::class, 'index']);
    Route::get('/withdrawals/{id}', [AdminWithdrawalController::class, 'show']);
    Route::post('/withdrawals/{id}/approve', [AdminWithdrawalController::class, 'approve']);
    Route::post('/withdrawals/{id}/reject', [AdminWithdrawalController::class, 'reject']);

    // Review Moderation
    Route::get('/reviews', [AdminReviewController::class, 'index']);
    Route::get('/reviews/{id}', [AdminReviewController::class, 'show']);
    Route::delete('/reviews/{id}', [AdminReviewController::class, 'destroy']);

    // Notification Broadcast
    Route::post('/notifications/broadcast', [AdminNotificationController::class, 'broadcast']);

    // Promo Code Setup
    Route::get('/promo-codes', [AdminPromoCodeController::class, 'index']);
    Route::get('/promo-codes/{id}', [AdminPromoCodeController::class, 'show']);
    Route::post('/promo-codes', [AdminPromoCodeController::class, 'store']);
    Route::put('/promo-codes/{id}', [AdminPromoCodeController::class, 'update']);
    Route::delete('/promo-codes/{id}', [AdminPromoCodeController::class, 'destroy']);
    Route::patch('/promo-codes/{id}/status', [AdminPromoCodeController::class, 'status']);
    Route::post('/promo-codes/{id}/restore', [AdminPromoCodeController::class, 'restore']);

    // System Settings Configuration
    Route::get('/settings', [AdminSystemSettingsController::class, 'index']);
    Route::put('/settings', [AdminSystemSettingsController::class, 'update']);
    Route::post('/settings/cache-refresh', [AdminSystemSettingsController::class, 'refreshCache']);

    // Audit Logs
    Route::get('/audit-logs', [AdminAuditLogController::class, 'index']);
    Route::get('/audit-logs/{id}', [AdminAuditLogController::class, 'show']);

    // Vehicle Type CRUD
    Route::get('/vehicle-types', [\App\Http\Controllers\Admin\VehicleTypeController::class, 'index']);
    Route::post('/vehicle-types', [\App\Http\Controllers\Admin\VehicleTypeController::class, 'store']);
    Route::get('/vehicle-types/{id}', [\App\Http\Controllers\Admin\VehicleTypeController::class, 'show']);
    Route::put('/vehicle-types/{id}', [\App\Http\Controllers\Admin\VehicleTypeController::class, 'update']);
    Route::delete('/vehicle-types/{id}', [\App\Http\Controllers\Admin\VehicleTypeController::class, 'destroy']);
    Route::patch('/vehicle-types/{id}/status', [\App\Http\Controllers\Admin\VehicleTypeController::class, 'status']);
    Route::post('/vehicle-types/{id}/restore', [\App\Http\Controllers\Admin\VehicleTypeController::class, 'restore']);
});
