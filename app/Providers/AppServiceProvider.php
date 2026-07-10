<?php

namespace App\Providers;

use App\Events\AdminAnnouncementEvent;
use App\Events\CouponReceivedEvent;
use App\Events\DriverDocumentApprovedEvent;
use App\Events\DriverDocumentRejectedEvent;
use App\Events\DriverLocationUpdated;
use App\Events\DriverStatusChanged;
use App\Events\MessageDelivered;
use App\Events\MessageRead;
use App\Events\MessageSent;
use App\Events\PaymentFailedEvent;
use App\Events\PaymentSucceededEvent;
use App\Events\PromotionEvent;
use App\Events\ReferralBonusEvent;
use App\Events\ReviewReceivedEvent;
use App\Events\RideAcceptedEvent;
use App\Events\RideArrivedEvent;
use App\Events\RideArrivingEvent;
use App\Events\RideCancelledEvent;
use App\Events\RideCompletedEvent;
use App\Events\RideRequestedEvent;
use App\Events\RideStartedEvent;
use App\Events\TypingStarted;
use App\Events\TypingStopped;
use App\Events\WalletCreditEvent;
use App\Events\WalletDebitEvent;
use App\Events\WalletTopupCompletedEvent;
use App\Events\WithdrawalApprovedEvent;
use App\Events\WithdrawalCompletedEvent;
use App\Events\WithdrawalRejectedEvent;
use App\Events\WithdrawalRequestedEvent;
use App\Listeners\BroadcastDriverLocation;
use App\Listeners\BroadcastDriverStatus;
use App\Listeners\BroadcastMessages;
use App\Listeners\BroadcastReview;
use App\Listeners\BroadcastRideStatus;
use App\Listeners\BroadcastTyping;
use App\Listeners\BroadcastWallet;
use App\Listeners\SendAdminNotification;
use App\Listeners\SendPaymentNotification;
use App\Listeners\SendPromotionNotification;
use App\Listeners\SendReviewNotification;
use App\Listeners\SendRideNotification;
use App\Listeners\SendWalletNotification;
use App\Models\Ride;
use App\Observers\RideObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
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
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        Ride::observe(RideObserver::class);

        // Ride Events
        Event::listen(RideRequestedEvent::class, SendRideNotification::class);
        Event::listen(RideAcceptedEvent::class, SendRideNotification::class);
        Event::listen(RideArrivingEvent::class, SendRideNotification::class);
        Event::listen(RideArrivedEvent::class, SendRideNotification::class);
        Event::listen(RideStartedEvent::class, SendRideNotification::class);
        Event::listen(RideCompletedEvent::class, SendRideNotification::class);
        Event::listen(RideCancelledEvent::class, SendRideNotification::class);

        // Wallet Events
        Event::listen(WalletTopupCompletedEvent::class, SendWalletNotification::class);
        Event::listen(WalletCreditEvent::class, SendWalletNotification::class);
        Event::listen(WalletDebitEvent::class, SendWalletNotification::class);
        Event::listen(WithdrawalRequestedEvent::class, SendWalletNotification::class);
        Event::listen(WithdrawalApprovedEvent::class, SendWalletNotification::class);
        Event::listen(WithdrawalRejectedEvent::class, SendWalletNotification::class);
        Event::listen(WithdrawalCompletedEvent::class, SendWalletNotification::class);

        // Payment Events
        Event::listen(PaymentSucceededEvent::class, SendPaymentNotification::class);
        Event::listen(PaymentFailedEvent::class, SendPaymentNotification::class);

        // Review Events
        Event::listen(ReviewReceivedEvent::class, SendReviewNotification::class);

        // Admin/Document/System Events
        Event::listen(DriverDocumentApprovedEvent::class, SendAdminNotification::class);
        Event::listen(DriverDocumentRejectedEvent::class, SendAdminNotification::class);
        Event::listen(AdminAnnouncementEvent::class, SendAdminNotification::class);
        Event::listen(ReferralBonusEvent::class, SendAdminNotification::class);

        // Promotion Events
        Event::listen(PromotionEvent::class, SendPromotionNotification::class);
        Event::listen(CouponReceivedEvent::class, SendPromotionNotification::class);

        // Real-Time Ride Status Listeners
        Event::listen(RideRequestedEvent::class, BroadcastRideStatus::class);
        Event::listen(RideAcceptedEvent::class, BroadcastRideStatus::class);
        Event::listen(RideArrivingEvent::class, BroadcastRideStatus::class);
        Event::listen(RideArrivedEvent::class, BroadcastRideStatus::class);
        Event::listen(RideStartedEvent::class, BroadcastRideStatus::class);
        Event::listen(RideCompletedEvent::class, BroadcastRideStatus::class);
        Event::listen(RideCancelledEvent::class, BroadcastRideStatus::class);
        Event::listen(PaymentSucceededEvent::class, BroadcastRideStatus::class);

        // Real-Time Wallet Listeners
        Event::listen(WalletTopupCompletedEvent::class, BroadcastWallet::class);
        Event::listen(WalletCreditEvent::class, BroadcastWallet::class);
        Event::listen(WalletDebitEvent::class, BroadcastWallet::class);
        Event::listen(WithdrawalRequestedEvent::class, BroadcastWallet::class);
        Event::listen(WithdrawalApprovedEvent::class, BroadcastWallet::class);
        Event::listen(WithdrawalRejectedEvent::class, BroadcastWallet::class);
        Event::listen(WithdrawalCompletedEvent::class, BroadcastWallet::class);

        // Real-Time Review Listeners
        Event::listen(ReviewReceivedEvent::class, BroadcastReview::class);

        // Real-Time Chat & Typing Listeners
        Event::listen(MessageSent::class, BroadcastMessages::class);
        Event::listen(MessageDelivered::class, BroadcastMessages::class);
        Event::listen(MessageRead::class, BroadcastMessages::class);
        Event::listen(TypingStarted::class, BroadcastTyping::class);
        Event::listen(TypingStopped::class, BroadcastTyping::class);

        // Real-Time Driver Location & Availability Listeners
        Event::listen(DriverLocationUpdated::class, BroadcastDriverLocation::class);
        Event::listen(DriverStatusChanged::class, BroadcastDriverStatus::class);
    }
}
