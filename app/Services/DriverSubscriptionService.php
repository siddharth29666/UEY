<?php

namespace App\Services;

use App\Enums\NotificationType;
use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use App\Events\WalletDebitEvent;
use App\Models\DriverCreditTransaction;
use App\Models\DriverProfile;
use App\Models\DriverSubscription;
use App\Models\SubscriptionPlan;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class DriverSubscriptionService
{
    public function __construct(
        protected ?StripeService $stripeService = null
    ) {}

    /**
     * Get all active subscription plans for drivers.
     */
    public function getAvailablePlans(): Collection
    {
        return SubscriptionPlan::where('status', true)
            ->orderBy('sort_order', 'asc')
            ->orderBy('id', 'asc')
            ->get();
    }

    /**
     * Purchase a subscription plan using driver's internal wallet balance (GBP).
     * Atomic, double-spending protected via lockForUpdate() on the driver's wallet row.
     */
    public function purchasePlan(DriverProfile $driver, SubscriptionPlan $plan): array
    {
        if (! $plan->status) {
            throw new \Exception('Selected subscription plan is not active.');
        }

        // Check if driver already has an active subscription
        $existingSub = $this->getCurrentSubscription($driver);
        if ($existingSub) {
            throw new \Exception('You already have an active subscription plan.');
        }

        return DB::transaction(function () use ($driver, $plan) {
            // Find or create driver's wallet
            $wallet = Wallet::firstOrCreate(
                ['user_id' => $driver->user_id],
                ['balance' => 0.00, 'currency' => 'GBP', 'status' => 'active']
            );

            // Lock wallet row for update to prevent double-spending & race conditions
            $wallet = Wallet::where('id', $wallet->id)->lockForUpdate()->first();

            $price = (float) $plan->price_gbp;
            $balanceBefore = (float) $wallet->balance;

            if ($balanceBefore < $price) {
                throw new \Exception('Insufficient wallet balance. Please top up your wallet to purchase this subscription plan.');
            }

            $balanceAfter = round($balanceBefore - $price, 2);

            // Deduct exact price from wallet
            $wallet->update(['balance' => $balanceAfter]);

            // Create wallet debit transaction
            $walletTx = WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'type' => 'debit',
                'transaction_type' => WalletTransactionType::SUBSCRIPTION_PURCHASE,
                'amount' => $price,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'status' => WalletTransactionStatus::COMPLETED,
                'reference' => 'SUB_PURCHASE_'.$plan->id.'_'.time(),
                'remarks' => 'Subscription plan purchase: '.$plan->name,
                'metadata' => [
                    'subscription_plan_id' => $plan->id,
                    'subscription_plan_name' => $plan->name,
                    'credits_allocated' => $plan->ride_credits,
                    'duration_days' => $plan->duration_days,
                    'amount_gbp' => $price,
                ],
            ]);

            // Mirror to audit ledger
            app(LedgerService::class)->createFromWalletTransaction($walletTx);

            // Fire WalletDebitEvent
            event(new WalletDebitEvent($wallet->user, NotificationType::WALLET_DEBIT, null, null, ['amount' => $price]));

            // Create and immediately activate DriverSubscription
            $subscription = DriverSubscription::create([
                'driver_profile_id' => $driver->id,
                'subscription_plan_id' => $plan->id,
                'stripe_checkout_session_id' => null,
                'stripe_payment_intent_id' => null,
                'stripe_subscription_id' => null,
                'amount_gbp' => $plan->price_gbp,
                'currency' => 'gbp',
                'credits_allocated' => $plan->ride_credits,
                'credits_used' => 0,
                'credits_remaining' => $plan->ride_credits,
                'starts_at' => now(),
                'expires_at' => now()->addDays($plan->duration_days),
                'status' => 'active',
                'payment_source' => 'wallet',
            ]);

            // Create subscription_purchase credit transaction
            DriverCreditTransaction::create([
                'driver_profile_id' => $driver->id,
                'driver_subscription_id' => $subscription->id,
                'type' => 'subscription_purchase',
                'amount' => $plan->ride_credits,
                'balance_before' => 0,
                'balance_after' => $plan->ride_credits,
                'reference' => 'SUB_PURCHASE_'.$subscription->id,
                'metadata' => [
                    'plan_name' => $plan->name,
                    'payment_source' => 'wallet',
                    'wallet_transaction_id' => $walletTx->id,
                    'amount_gbp' => $price,
                ],
            ]);

            return [
                'subscription' => $subscription->fresh(['subscriptionPlan']),
                'wallet' => [
                    'balance_before' => $balanceBefore,
                    'amount_deducted' => $price,
                    'balance_after' => $balanceAfter,
                ],
            ];
        });
    }

    /**
     * Activate a pending subscription after verified Stripe payment (Backward Compatibility / Historical).
     */
    public function handleSuccessfulPayment(string $paymentIdentifier, array $payload = []): ?DriverSubscription
    {
        return DB::transaction(function () use ($paymentIdentifier, $payload) {
            // Locate subscription by PaymentIntent ID, Checkout Session ID, or payload metadata
            $metadata = $payload['data']['object']['metadata'] ?? [];
            $subscriptionId = $metadata['driver_subscription_id'] ?? null;

            $query = DriverSubscription::query();
            if ($subscriptionId) {
                $query->where('id', $subscriptionId);
            } else {
                $query->where(function ($q) use ($paymentIdentifier) {
                    $q->where('stripe_payment_intent_id', $paymentIdentifier)
                        ->orWhere('stripe_checkout_session_id', $paymentIdentifier);
                });
            }

            $subscription = $query->lockForUpdate()->first();

            if (! $subscription) {
                return null;
            }

            // Idempotency check: if already active, skip duplicate allocation
            if ($subscription->status === 'active') {
                return $subscription;
            }

            $plan = $subscription->subscriptionPlan;
            $durationDays = $plan ? $plan->duration_days : 30;

            $startsAt = now();
            $expiresAt = now()->addDays($durationDays);

            $subscription->update([
                'status' => 'active',
                'starts_at' => $startsAt,
                'expires_at' => $expiresAt,
                'payment_source' => 'stripe',
            ]);

            // Create subscription_purchase credit ledger transaction
            DriverCreditTransaction::create([
                'driver_profile_id' => $subscription->driver_profile_id,
                'driver_subscription_id' => $subscription->id,
                'type' => 'subscription_purchase',
                'amount' => $subscription->credits_allocated,
                'balance_before' => 0,
                'balance_after' => $subscription->credits_allocated,
                'reference' => 'SUB_PURCHASE_'.$subscription->id,
                'metadata' => [
                    'plan_name' => $plan ? $plan->name : 'Subscription Plan',
                    'stripe_identifier' => $paymentIdentifier,
                    'payment_source' => 'stripe',
                ],
            ]);

            return $subscription;
        });
    }

    /**
     * Get current active subscription for a driver.
     */
    public function getCurrentSubscription(DriverProfile $driver): ?DriverSubscription
    {
        return DriverSubscription::with('subscriptionPlan')
            ->where('driver_profile_id', $driver->id)
            ->where('status', 'active')
            ->where('starts_at', '<=', now())
            ->where('expires_at', '>=', now())
            ->where('credits_remaining', '>', 0)
            ->orderBy('id', 'desc')
            ->first();
    }

    /**
     * Get paginated subscription purchase history for a driver.
     */
    public function getSubscriptionHistory(DriverProfile $driver, int $perPage = 15): LengthAwarePaginator
    {
        return DriverSubscription::with('subscriptionPlan')
            ->where('driver_profile_id', $driver->id)
            ->orderBy('id', 'desc')
            ->paginate($perPage);
    }

    /**
     * Get current credit balance summary for driver.
     */
    public function getAvailableCredits(DriverProfile $driver): array
    {
        $subscription = $this->getCurrentSubscription($driver);
        $wallet = $driver->user->wallet;
        $walletBalance = $wallet ? (float) $wallet->balance : 0.00;

        if (! $subscription) {
            return [
                'has_active_subscription' => false,
                'plan_name' => null,
                'credits_allocated' => 0,
                'credits_used' => 0,
                'credits_remaining' => 0,
                'expires_at' => null,
                'days_remaining' => 0,
                'wallet_balance' => $walletBalance,
                'payment_source' => null,
            ];
        }

        $daysRemaining = 0;
        if ($subscription->expires_at && $subscription->expires_at->isFuture()) {
            $daysRemaining = (int) now()->diffInDays($subscription->expires_at, false);
            if ($daysRemaining < 0) {
                $daysRemaining = 0;
            }
        }

        return [
            'has_active_subscription' => true,
            'plan_name' => $subscription->subscriptionPlan?->name,
            'credits_allocated' => (int) $subscription->credits_allocated,
            'credits_used' => (int) $subscription->credits_used,
            'credits_remaining' => (int) $subscription->credits_remaining,
            'expires_at' => $subscription->expires_at?->toIso8601String(),
            'days_remaining' => $daysRemaining,
            'wallet_balance' => $walletBalance,
            'payment_source' => $subscription->payment_source ?? 'wallet',
        ];
    }

    /**
     * Check whether a driver can accept a ride.
     */
    public function canAcceptRide(DriverProfile $driver): bool
    {
        $subscription = $this->getCurrentSubscription($driver);

        return $subscription !== null && $subscription->credits_remaining > 0;
    }

    /**
     * OPTION B: Atomically consume 1 ride credit when driver accepts a ride.
     * Deducts 1 credit immediately. Non-refundable upon rider or driver cancellation.
     * Safe against double accepts / concurrency via lockForUpdate().
     */
    public function consumeRideCredit(DriverProfile $driver, int $rideId, int $rideRequestId): array
    {
        return DB::transaction(function () use ($driver, $rideId, $rideRequestId) {
            // Check if credit was already deducted for this request (Idempotency)
            $existingTx = DriverCreditTransaction::where('driver_profile_id', $driver->id)
                ->where('ride_request_id', $rideRequestId)
                ->where('type', 'ride_accept')
                ->first();

            if ($existingTx) {
                $sub = $existingTx->driverSubscription;

                return [
                    'credits_remaining' => $sub ? (int) $sub->credits_remaining : 0,
                    'expires_at' => $sub && $sub->expires_at ? $sub->expires_at->toIso8601String() : null,
                ];
            }

            // Lock active subscription row for update
            $subscription = DriverSubscription::where('driver_profile_id', $driver->id)
                ->where('status', 'active')
                ->where('starts_at', '<=', now())
                ->where('expires_at', '>=', now())
                ->where('credits_remaining', '>', 0)
                ->orderBy('id', 'desc')
                ->lockForUpdate()
                ->first();

            if (! $subscription || $subscription->credits_remaining <= 0) {
                throw new \Exception('You do not have enough ride credits. Please purchase a subscription plan.');
            }

            $before = (int) $subscription->credits_remaining;
            $subscription->credits_used += 1;
            $subscription->credits_remaining -= 1;

            $subscription->save();
            $after = (int) $subscription->credits_remaining;

            // Create ride_accept ledger transaction
            DriverCreditTransaction::create([
                'driver_profile_id' => $driver->id,
                'driver_subscription_id' => $subscription->id,
                'ride_id' => $rideId,
                'ride_request_id' => $rideRequestId,
                'type' => 'ride_accept',
                'amount' => -1,
                'balance_before' => $before,
                'balance_after' => $after,
                'reference' => 'RIDE_ACCEPT_'.$rideId,
                'metadata' => [
                    'ride_id' => $rideId,
                    'ride_request_id' => $rideRequestId,
                ],
            ]);

            return [
                'credits_remaining' => $after,
                'expires_at' => $subscription->expires_at?->toIso8601String(),
            ];
        });
    }

    /**
     * Expire active subscriptions where expires_at < now().
     */
    public function expireSubscriptions(): int
    {
        $expiredCount = 0;

        $expiredSubscriptions = DriverSubscription::where('status', 'active')
            ->where('expires_at', '<', now())
            ->get();

        foreach ($expiredSubscriptions as $sub) {
            DB::transaction(function () use ($sub, &$expiredCount) {
                $sub->lockForUpdate();
                if ($sub->status !== 'active') {
                    return;
                }

                $before = (int) $sub->credits_remaining;

                $sub->update([
                    'status' => 'expired',
                ]);

                // Create expiry audit record
                DriverCreditTransaction::create([
                    'driver_profile_id' => $sub->driver_profile_id,
                    'driver_subscription_id' => $sub->id,
                    'type' => 'expiry',
                    'amount' => 0,
                    'balance_before' => $before,
                    'balance_after' => $before,
                    'reference' => 'EXPIRY_'.$sub->id,
                    'metadata' => [
                        'expired_at' => now()->toIso8601String(),
                        'unused_credits' => $before,
                    ],
                ]);

                $expiredCount++;
            });
        }

        return $expiredCount;
    }
}
