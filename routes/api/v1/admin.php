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
    Route::get('/settings/{key}', [AdminSystemSettingsController::class, 'show']);
    Route::put('/settings', [AdminSystemSettingsController::class, 'update']);
    Route::delete('/settings/{key}', [AdminSystemSettingsController::class, 'destroy']);
    Route::post('/settings/cache-refresh', [AdminSystemSettingsController::class, 'refreshCache']);

    // Cancellation Reasons Management
    Route::get('/cancellation-reasons', [\App\Http\Controllers\Admin\AdminCancellationReasonController::class, 'index']);
    Route::post('/cancellation-reasons', [\App\Http\Controllers\Admin\AdminCancellationReasonController::class, 'store']);
    Route::get('/cancellation-reasons/{id}', [\App\Http\Controllers\Admin\AdminCancellationReasonController::class, 'show']);
    Route::put('/cancellation-reasons/{id}', [\App\Http\Controllers\Admin\AdminCancellationReasonController::class, 'update']);
    Route::patch('/cancellation-reasons/{id}/status', [\App\Http\Controllers\Admin\AdminCancellationReasonController::class, 'status']);
    Route::delete('/cancellation-reasons/{id}', [\App\Http\Controllers\Admin\AdminCancellationReasonController::class, 'destroy']);

    // FAQ Categories & FAQ Management
    Route::get('/faq-categories', [\App\Http\Controllers\Admin\AdminFaqCategoryController::class, 'index']);
    Route::post('/faq-categories', [\App\Http\Controllers\Admin\AdminFaqCategoryController::class, 'store']);
    Route::get('/faq-categories/{id}', [\App\Http\Controllers\Admin\AdminFaqCategoryController::class, 'show']);
    Route::put('/faq-categories/{id}', [\App\Http\Controllers\Admin\AdminFaqCategoryController::class, 'update']);
    Route::delete('/faq-categories/{id}', [\App\Http\Controllers\Admin\AdminFaqCategoryController::class, 'destroy']);

    Route::get('/faqs', [\App\Http\Controllers\Admin\AdminFaqController::class, 'index']);
    Route::post('/faqs', [\App\Http\Controllers\Admin\AdminFaqController::class, 'store']);
    Route::get('/faqs/{id}', [\App\Http\Controllers\Admin\AdminFaqController::class, 'show']);
    Route::put('/faqs/{id}', [\App\Http\Controllers\Admin\AdminFaqController::class, 'update']);
    Route::patch('/faqs/{id}/status', [\App\Http\Controllers\Admin\AdminFaqController::class, 'status']);
    Route::delete('/faqs/{id}', [\App\Http\Controllers\Admin\AdminFaqController::class, 'destroy']);

    // Contact Submissions Management
    Route::get('/contact-submissions', [\App\Http\Controllers\Admin\AdminContactSubmissionController::class, 'index']);
    Route::get('/contact-submissions/{id}', [\App\Http\Controllers\Admin\AdminContactSubmissionController::class, 'show']);
    Route::put('/contact-submissions/{id}', [\App\Http\Controllers\Admin\AdminContactSubmissionController::class, 'update']);
    Route::delete('/contact-submissions/{id}', [\App\Http\Controllers\Admin\AdminContactSubmissionController::class, 'destroy']);

    // Legal Pages Management
    Route::get('/legal-pages', [\App\Http\Controllers\Admin\AdminLegalPageController::class, 'index']);
    Route::post('/legal-pages', [\App\Http\Controllers\Admin\AdminLegalPageController::class, 'store']);
    Route::get('/legal-pages/{id}', [\App\Http\Controllers\Admin\AdminLegalPageController::class, 'show']);
    Route::put('/legal-pages/{id}', [\App\Http\Controllers\Admin\AdminLegalPageController::class, 'update']);
    Route::patch('/legal-pages/{id}/status', [\App\Http\Controllers\Admin\AdminLegalPageController::class, 'status']);
    Route::delete('/legal-pages/{id}', [\App\Http\Controllers\Admin\AdminLegalPageController::class, 'destroy']);

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

    // ADMIN SUBSCRIPTION MANAGEMENT FEATURE TEMPORARILY DISABLED
    // Re-enable this route group by setting DRIVER_SUBSCRIPTION_ENABLED=true in .env / config.
    if (config('app.driver_subscription_enabled', false)) {
        Route::get('/subscription-plans', [\App\Http\Controllers\Admin\AdminSubscriptionPlanController::class, 'index']);
        Route::post('/subscription-plans', [\App\Http\Controllers\Admin\AdminSubscriptionPlanController::class, 'store']);
        Route::get('/subscription-plans/{id}', [\App\Http\Controllers\Admin\AdminSubscriptionPlanController::class, 'show']);
        Route::put('/subscription-plans/{id}', [\App\Http\Controllers\Admin\AdminSubscriptionPlanController::class, 'update']);
        Route::delete('/subscription-plans/{id}', [\App\Http\Controllers\Admin\AdminSubscriptionPlanController::class, 'destroy']);
        Route::patch('/subscription-plans/{id}/status', [\App\Http\Controllers\Admin\AdminSubscriptionPlanController::class, 'status']);
        Route::post('/subscription-plans/{id}/restore', [\App\Http\Controllers\Admin\AdminSubscriptionPlanController::class, 'restore']);
        Route::get('/driver-subscriptions', [\App\Http\Controllers\Admin\AdminSubscriptionPlanController::class, 'driverSubscriptions']);
        Route::get('/driver-subscriptions/{id}', [\App\Http\Controllers\Admin\AdminSubscriptionPlanController::class, 'showDriverSubscription']);
        Route::get('/driver-credit-transactions', [\App\Http\Controllers\Admin\AdminSubscriptionPlanController::class, 'creditTransactions']);
    }

    // Admin Vehicle Management & Approval Flow
    Route::get('/vehicles/pending', [\App\Http\Controllers\Admin\AdminVehicleController::class, 'pending']);
    Route::get('/vehicles', [\App\Http\Controllers\Admin\AdminVehicleController::class, 'index']);
    Route::get('/vehicles/{id}', [\App\Http\Controllers\Admin\AdminVehicleController::class, 'show']);
    Route::post('/vehicles/{id}/approve', [\App\Http\Controllers\Admin\AdminVehicleController::class, 'approve']);
    Route::post('/vehicles/{id}/reject', [\App\Http\Controllers\Admin\AdminVehicleController::class, 'reject']);
    Route::patch('/vehicles/{id}/status', [\App\Http\Controllers\Admin\AdminVehicleController::class, 'status']);

    // Phase 17 — Reports & Export System
    Route::get('/reports/revenue/daily', [\App\Http\Controllers\Admin\AdminReportController::class, 'dailyRevenue']);
    Route::get('/reports/revenue/weekly', [\App\Http\Controllers\Admin\AdminReportController::class, 'weeklyRevenue']);
    Route::get('/reports/revenue/monthly', [\App\Http\Controllers\Admin\AdminReportController::class, 'monthlyRevenue']);
    Route::get('/reports/revenue/custom', [\App\Http\Controllers\Admin\AdminReportController::class, 'customRevenue']);
    Route::get('/reports/platform-commission', [\App\Http\Controllers\Admin\AdminReportController::class, 'platformCommission']);
    Route::get('/reports/driver-earnings', [\App\Http\Controllers\Admin\AdminReportController::class, 'driverEarnings']);
    Route::get('/reports/promo-discounts', [\App\Http\Controllers\Admin\AdminReportController::class, 'promoDiscounts']);
    Route::get('/reports/referral-rewards', [\App\Http\Controllers\Admin\AdminReportController::class, 'referralRewards']);
    Route::get('/reports/wallet-statement', [\App\Http\Controllers\Admin\AdminReportController::class, 'walletStatement']);
    Route::get('/reports/wallet-credit-debit', [\App\Http\Controllers\Admin\AdminReportController::class, 'creditDebitHistory']);
    Route::get('/reports/cashouts', [\App\Http\Controllers\Admin\AdminReportController::class, 'cashoutReport']);
    Route::get('/reports/ledger', [\App\Http\Controllers\Admin\AdminReportController::class, 'ledgerReport']);
});
