<?php

namespace App\Services;

use App\Models\DriverCreditTransaction;
use App\Models\DriverProfile;
use App\Models\DriverSubscription;
use App\Models\SubscriptionPlan;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class DriverSubscriptionService
{
    public function __construct(
        protected StripeService $stripeService
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
     * Initiate a subscription purchase via Stripe (EUR).
     */
    public function purchasePlan(DriverProfile $driver, SubscriptionPlan $plan): array
    {
        if (! $plan->status) {
            throw new \Exception('Selected subscription plan is not active.');
        }

        return DB::transaction(function () use ($driver, $plan) {
            // Create pending subscription record
            $subscription = DriverSubscription::create([
                'driver_profile_id' => $driver->id,
                'subscription_plan_id' => $plan->id,
                'amount_eur' => $plan->price_eur,
                'currency' => 'eur',
                'credits_allocated' => $plan->ride_credits,
                'credits_used' => 0,
                'credits_remaining' => $plan->ride_credits,
                'status' => 'pending',
            ]);

            $metadata = [
                'driver_profile_id' => (string) $driver->id,
                'subscription_plan_id' => (string) $plan->id,
                'driver_subscription_id' => (string) $subscription->id,
                'plan_name' => $plan->name,
                'type' => 'driver_subscription',
            ];

            // Create Stripe PaymentIntent in EUR
            $paymentIntent = $this->stripeService->createPaymentIntent(
                (float) $plan->price_eur,
                'eur',
                $metadata
            );

            // Also create a Checkout Session as an alternative option
            $checkoutSession = $this->stripeService->createCheckoutSession(
                (float) $plan->price_eur,
                'eur',
                $metadata
            );

            $subscription->update([
                'stripe_payment_intent_id' => $paymentIntent->id,
                'stripe_checkout_session_id' => $checkoutSession->id,
            ]);

            return [
                'subscription' => $subscription->fresh(),
                'payment_intent_id' => $paymentIntent->id,
                'client_secret' => $paymentIntent->client_secret,
                'checkout_session_id' => $checkoutSession->id,
                'checkout_url' => $checkoutSession->url,
            ];
        });
    }

    /**
     * Activate a pending subscription after verified Stripe payment (Idempotent).
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

        if (! $subscription) {
            return [
                'has_active_subscription' => false,
                'plan_name' => null,
                'credits_allocated' => 0,
                'credits_used' => 0,
                'credits_remaining' => 0,
                'expires_at' => null,
                'days_remaining' => 0,
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

            if ($subscription->credits_remaining == 0) {
                // All credits exhausted
            }

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
