<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Support\Facades\RateLimiter::for('api', function (\Illuminate\Http\Request $request) {
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        \Illuminate\Support\Facades\RateLimiter::for('auth', function (\Illuminate\Http\Request $request) {
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(5)->by($request->ip());
        });

        \App\Models\Ride::observe(\App\Observers\RideObserver::class);

        // Ride Events
        \Illuminate\Support\Facades\Event::listen(\App\Events\RideRequestedEvent::class, \App\Listeners\SendRideNotification::class);
        \Illuminate\Support\Facades\Event::listen(\App\Events\RideAcceptedEvent::class, \App\Listeners\SendRideNotification::class);
        \Illuminate\Support\Facades\Event::listen(\App\Events\RideArrivingEvent::class, \App\Listeners\SendRideNotification::class);
        \Illuminate\Support\Facades\Event::listen(\App\Events\RideArrivedEvent::class, \App\Listeners\SendRideNotification::class);
        \Illuminate\Support\Facades\Event::listen(\App\Events\RideStartedEvent::class, \App\Listeners\SendRideNotification::class);
        \Illuminate\Support\Facades\Event::listen(\App\Events\RideCompletedEvent::class, \App\Listeners\SendRideNotification::class);
        \Illuminate\Support\Facades\Event::listen(\App\Events\RideCancelledEvent::class, \App\Listeners\SendRideNotification::class);

        // Wallet Events
        \Illuminate\Support\Facades\Event::listen(\App\Events\WalletTopupCompletedEvent::class, \App\Listeners\SendWalletNotification::class);
        \Illuminate\Support\Facades\Event::listen(\App\Events\WalletCreditEvent::class, \App\Listeners\SendWalletNotification::class);
        \Illuminate\Support\Facades\Event::listen(\App\Events\WalletDebitEvent::class, \App\Listeners\SendWalletNotification::class);
        \Illuminate\Support\Facades\Event::listen(\App\Events\WithdrawalRequestedEvent::class, \App\Listeners\SendWalletNotification::class);
        \Illuminate\Support\Facades\Event::listen(\App\Events\WithdrawalApprovedEvent::class, \App\Listeners\SendWalletNotification::class);
        \Illuminate\Support\Facades\Event::listen(\App\Events\WithdrawalRejectedEvent::class, \App\Listeners\SendWalletNotification::class);
        \Illuminate\Support\Facades\Event::listen(\App\Events\WithdrawalCompletedEvent::class, \App\Listeners\SendWalletNotification::class);

        // Payment Events
        \Illuminate\Support\Facades\Event::listen(\App\Events\PaymentSucceededEvent::class, \App\Listeners\SendPaymentNotification::class);
        \Illuminate\Support\Facades\Event::listen(\App\Events\PaymentFailedEvent::class, \App\Listeners\SendPaymentNotification::class);

        // Review Events
        \Illuminate\Support\Facades\Event::listen(\App\Events\ReviewReceivedEvent::class, \App\Listeners\SendReviewNotification::class);

        // Admin/Document/System Events
        \Illuminate\Support\Facades\Event::listen(\App\Events\DriverDocumentApprovedEvent::class, \App\Listeners\SendAdminNotification::class);
        \Illuminate\Support\Facades\Event::listen(\App\Events\DriverDocumentRejectedEvent::class, \App\Listeners\SendAdminNotification::class);
        \Illuminate\Support\Facades\Event::listen(\App\Events\AdminAnnouncementEvent::class, \App\Listeners\SendAdminNotification::class);
        \Illuminate\Support\Facades\Event::listen(\App\Events\ReferralBonusEvent::class, \App\Listeners\SendAdminNotification::class);

        // Promotion Events
        \Illuminate\Support\Facades\Event::listen(\App\Events\PromotionEvent::class, \App\Listeners\SendPromotionNotification::class);
        \Illuminate\Support\Facades\Event::listen(\App\Events\CouponReceivedEvent::class, \App\Listeners\SendPromotionNotification::class);
    }
}
