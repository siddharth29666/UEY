<?php

namespace Tests\Feature;

use App\Enums\RideRequestStatus;
use App\Enums\RideStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\DriverCreditTransaction;
use App\Models\DriverProfile;
use App\Models\DriverSubscription;
use App\Models\Ride;
use App\Models\RideRequest;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\VehicleType;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DriverSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $driverUser;
    protected DriverProfile $driverProfile;
    protected User $riderUser;
    protected VehicleType $vehicleType;
    protected SubscriptionPlan $planA;
    protected SubscriptionPlan $planB;
    protected Wallet $driverWallet;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.driver_subscription_enabled' => true]);

        // Admin User
        $this->adminUser = User::create([
            'name' => 'Admin User',
            'email' => 'admin.sub@example.com',
            'phone' => '+447911111111',
            'password' => bcrypt('password'),
            'role' => UserRole::ADMIN,
            'status' => UserStatus::ACTIVE,
        ]);

        // Vehicle Type
        $this->vehicleType = VehicleType::create([
            'name' => 'Subscription Sedan',
            'base_fare' => 5.00,
            'per_km_rate' => 1.50,
            'per_minute_rate' => 0.20,
            'minimum_fare' => 7.00,
            'capacity' => 4,
            'is_active' => true,
        ]);

        // Driver User & Profile
        $this->driverUser = User::create([
            'name' => 'Dave Driver',
            'email' => 'dave.driver@example.com',
            'phone' => '+447911222222',
            'password' => bcrypt('password'),
            'role' => UserRole::DRIVER,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->driverProfile = DriverProfile::create([
            'user_id' => $this->driverUser->id,
            'vehicle_type_id' => $this->vehicleType->id,
            'license_number' => 'DL-SUB-999',
            'is_online' => true,
            'is_available' => true,
        ]);

        // Driver Wallet
        $this->driverWallet = Wallet::create([
            'user_id' => $this->driverUser->id,
            'balance' => 0.00,
            'currency' => 'GBP',
            'status' => 'active',
        ]);

        // Rider User
        $this->riderUser = User::create([
            'name' => 'Rachel Rider',
            'email' => 'rachel.rider@example.com',
            'phone' => '+447911333333',
            'password' => bcrypt('password'),
            'role' => UserRole::RIDER,
            'status' => UserStatus::ACTIVE,
        ]);

        // Subscription Plans (GBP)
        $this->planA = SubscriptionPlan::create([
            'name' => 'Plan A',
            'description' => 'Starter 20 Ride Credits',
            'price_gbp' => 10.00,
            'ride_credits' => 20,
            'duration_days' => 30,
            'status' => true,
            'sort_order' => 1,
        ]);

        $this->planB = SubscriptionPlan::create([
            'name' => 'Plan B',
            'description' => 'Pro 50 Ride Credits',
            'price_gbp' => 20.00,
            'ride_credits' => 50,
            'duration_days' => 30,
            'status' => true,
            'sort_order' => 2,
        ]);
    }

    /**
     * Admin Plan CRUD test.
     */
    public function test_admin_can_manage_subscription_plans(): void
    {
        Sanctum::actingAs($this->adminUser, ['role:admin']);

        // Create Plan
        $response = $this->postJson('/api/v1/admin/subscription-plans', [
            'name' => 'Plan C',
            'description' => 'Ultimate 100 Ride Credits',
            'price_gbp' => 35.00,
            'ride_credits' => 100,
            'duration_days' => 30,
            'status' => true,
            'sort_order' => 3,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'Plan C',
                    'price_gbp' => 35.00,
                    'currency' => 'GBP',
                    'ride_credits' => 100,
                ],
            ]);

        $planId = $response->json('data.id');

        // Update Plan
        $updateResponse = $this->putJson("/api/v1/admin/subscription-plans/{$planId}", [
            'price_gbp' => 30.00,
        ]);
        $updateResponse->assertStatus(200)
            ->assertJson(['data' => ['price_gbp' => 30.00]]);

        // Toggle Status
        $statusResponse = $this->patchJson("/api/v1/admin/subscription-plans/{$planId}/status", [
            'active' => false,
        ]);
        $statusResponse->assertStatus(200)
            ->assertJson(['data' => ['status' => false]]);
    }

    /**
     * Inactive plans hidden from driver listing.
     */
    public function test_inactive_plans_hidden_from_driver_listing(): void
    {
        $this->planB->update(['status' => false]);

        Sanctum::actingAs($this->driverUser, ['role:driver']);

        $response = $this->getJson('/api/v1/driver/subscription/plans');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $plans = $response->json('plans');
        $this->assertCount(1, $plans);
        $this->assertEquals('Plan A', $plans[0]['name']);
    }

    /**
     * Test 1: Driver purchases subscription using internal wallet balance.
     */
    public function test_driver_can_purchase_subscription_via_internal_wallet(): void
    {
        // Set wallet balance to €25.00
        $this->driverWallet->update(['balance' => 25.00]);

        Sanctum::actingAs($this->driverUser, ['role:driver']);

        $response = $this->postJson('/api/v1/driver/subscription/purchase', [
            'subscription_plan_id' => $this->planA->id,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Subscription purchased successfully.',
                'data' => [
                    'wallet' => [
                        'balance_before' => 25.00,
                        'amount_deducted' => 10.00,
                        'balance_after' => 15.00,
                    ],
                ],
            ]);

        // Wallet balance updated
        $this->driverWallet->refresh();
        $this->assertEquals(15.00, (float) $this->driverWallet->balance);

        // Subscription activated immediately
        $this->assertDatabaseHas('driver_subscriptions', [
            'driver_profile_id' => $this->driverProfile->id,
            'subscription_plan_id' => $this->planA->id,
            'amount_gbp' => 10.00,
            'status' => 'active',
            'payment_source' => 'wallet',
            'credits_allocated' => 20,
            'credits_remaining' => 20,
            'credits_used' => 0,
        ]);

        // Wallet debit transaction created
        $this->assertDatabaseHas('wallet_transactions', [
            'wallet_id' => $this->driverWallet->id,
            'type' => 'debit',
            'transaction_type' => 'subscription_purchase',
            'amount' => 10.00,
            'balance_before' => 25.00,
            'balance_after' => 15.00,
        ]);

        // Driver credit ledger transaction created
        $this->assertDatabaseHas('driver_credit_transactions', [
            'driver_profile_id' => $this->driverProfile->id,
            'type' => 'subscription_purchase',
            'amount' => 20,
        ]);
    }

    /**
     * Test 2: Purchase fails with HTTP 422 when wallet balance is insufficient.
     */
    public function test_purchase_fails_with_insufficient_wallet_balance(): void
    {
        // Set wallet balance to €5.00 (plan price is €10.00)
        $this->driverWallet->update(['balance' => 5.00]);

        Sanctum::actingAs($this->driverUser, ['role:driver']);

        $response = $this->postJson('/api/v1/driver/subscription/purchase', [
            'subscription_plan_id' => $this->planA->id,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Insufficient wallet balance. Please top up your wallet to purchase this subscription plan.',
            ]);

        // Wallet balance unchanged
        $this->driverWallet->refresh();
        $this->assertEquals(5.00, (float) $this->driverWallet->balance);

        // No subscription created
        $this->assertDatabaseMissing('driver_subscriptions', [
            'driver_profile_id' => $this->driverProfile->id,
        ]);
    }

    /**
     * Test 3: Purchase succeeds when wallet balance exactly equals plan price.
     */
    public function test_purchase_succeeds_with_exact_wallet_balance(): void
    {
        // Set wallet balance to exactly €10.00
        $this->driverWallet->update(['balance' => 10.00]);

        Sanctum::actingAs($this->driverUser, ['role:driver']);

        $response = $this->postJson('/api/v1/driver/subscription/purchase', [
            'subscription_plan_id' => $this->planA->id,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'wallet' => [
                        'balance_before' => 10.00,
                        'amount_deducted' => 10.00,
                        'balance_after' => 0.00,
                    ],
                ],
            ]);

        $this->driverWallet->refresh();
        $this->assertEquals(0.00, (float) $this->driverWallet->balance);
    }

    /**
     * Test 4: Stripe is not used for subscription purchase.
     */
    public function test_stripe_is_not_used_for_subscription_purchase(): void
    {
        $this->driverWallet->update(['balance' => 50.00]);

        Sanctum::actingAs($this->driverUser, ['role:driver']);

        $response = $this->postJson('/api/v1/driver/subscription/purchase', [
            'subscription_plan_id' => $this->planA->id,
        ]);

        $response->assertStatus(200);

        // Response should NOT contain Stripe keys
        $responseData = $response->json('data');
        $this->assertArrayNotHasKey('client_secret', $responseData);
        $this->assertArrayNotHasKey('checkout_url', $responseData);
        $this->assertArrayNotHasKey('payment_intent_id', $responseData);
    }

    /**
     * Test 5: Wallet Top-Up still uses Stripe.
     */
    public function test_wallet_topup_still_uses_stripe(): void
    {
        Sanctum::actingAs($this->driverUser, ['role:driver']);

        $response = $this->postJson('/api/v1/wallet/top-up', [
            'amount' => 50.00,
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'data' => ['client_secret', 'payment_intent_id'],
            ]);
    }

    /**
     * Test 6: Double Purchase Protection — Driver with active subscription cannot purchase another plan.
     */
    public function test_double_purchase_protection_when_active_subscription_exists(): void
    {
        $this->driverWallet->update(['balance' => 50.00]);

        Sanctum::actingAs($this->driverUser, ['role:driver']);

        // First purchase succeeds
        $this->postJson('/api/v1/driver/subscription/purchase', [
            'subscription_plan_id' => $this->planA->id,
        ])->assertStatus(200);

        // Second purchase fails
        $response = $this->postJson('/api/v1/driver/subscription/purchase', [
            'subscription_plan_id' => $this->planB->id,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'You already have an active subscription plan.',
            ]);
    }

    /**
     * Test 7 (OPTION B - Test 1): Driver with credits accepts ride -> 1 credit deducted immediately.
     */
    public function test_option_b_driver_accepts_ride_deducts_1_credit_immediately(): void
    {
        // Give driver active subscription with 10 credits
        $subscription = DriverSubscription::create([
            'driver_profile_id' => $this->driverProfile->id,
            'subscription_plan_id' => $this->planA->id,
            'amount_gbp' => 10.00,
            'currency' => 'gbp',
            'credits_allocated' => 10,
            'credits_used' => 0,
            'credits_remaining' => 10,
            'starts_at' => now()->subMinute(),
            'expires_at' => now()->addDays(30),
            'status' => 'active',
            'payment_source' => 'wallet',
        ]);

        // Create ride and request
        $ride = Ride::create([
            'rider_id' => $this->riderUser->id,
            'vehicle_type_id' => $this->vehicleType->id,
            'pickup_address' => 'Piccadilly',
            'destination_address' => 'Soho',
            'pickup_latitude' => 51.5,
            'pickup_longitude' => -0.1,
            'destination_latitude' => 51.6,
            'destination_longitude' => -0.2,
            'status' => RideStatus::PENDING,
            'estimated_distance' => 3.0,
            'estimated_duration' => 10,
            'estimated_fare' => 10.00,
        ]);

        $rideRequest = RideRequest::create([
            'ride_id' => $ride->id,
            'driver_profile_id' => $this->driverProfile->id,
            'status' => RideRequestStatus::PENDING,
        ]);

        Sanctum::actingAs($this->driverUser, ['role:driver']);

        $response = $this->postJson("/api/v1/driver/ride-requests/{$rideRequest->id}/accept");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'subscription' => [
                    'credits_remaining' => 9,
                ],
            ]);

        $subscription->refresh();
        $this->assertEquals(9, $subscription->credits_remaining);
        $this->assertEquals(1, $subscription->credits_used);
    }

    /**
     * Test 8 (OPTION B - Test 2 & 3): Cancellation by Rider or Driver does NOT refund credit.
     */
    public function test_option_b_cancellation_does_not_refund_credit(): void
    {
        $subscription = DriverSubscription::create([
            'driver_profile_id' => $this->driverProfile->id,
            'subscription_plan_id' => $this->planA->id,
            'amount_gbp' => 10.00,
            'currency' => 'gbp',
            'credits_allocated' => 10,
            'credits_used' => 0,
            'credits_remaining' => 10,
            'starts_at' => now()->subMinute(),
            'expires_at' => now()->addDays(30),
            'status' => 'active',
            'payment_source' => 'wallet',
        ]);

        $ride = Ride::create([
            'rider_id' => $this->riderUser->id,
            'vehicle_type_id' => $this->vehicleType->id,
            'pickup_address' => 'Piccadilly',
            'destination_address' => 'Soho',
            'pickup_latitude' => 51.5,
            'pickup_longitude' => -0.1,
            'destination_latitude' => 51.6,
            'destination_longitude' => -0.2,
            'status' => RideStatus::PENDING,
            'estimated_distance' => 3.0,
            'estimated_duration' => 10,
            'estimated_fare' => 10.00,
        ]);

        $rideRequest = RideRequest::create([
            'ride_id' => $ride->id,
            'driver_profile_id' => $this->driverProfile->id,
            'status' => RideRequestStatus::PENDING,
        ]);

        // Driver accepts -> credits become 9
        Sanctum::actingAs($this->driverUser, ['role:driver']);
        $this->postJson("/api/v1/driver/ride-requests/{$rideRequest->id}/accept");

        $subscription->refresh();
        $this->assertEquals(9, $subscription->credits_remaining);

        // Rider cancels ride
        Sanctum::actingAs($this->riderUser, ['role:rider']);
        $this->postJson("/api/v1/rides/{$ride->id}/cancel", ['reason' => 'Changed my mind']);

        // Credits MUST REMAIN 9 (No refund!)
        $subscription->refresh();
        $this->assertEquals(9, $subscription->credits_remaining);
    }

    /**
     * Test 9: Expired subscription cannot accept rides and command expires it.
     */
    public function test_expired_subscription_cannot_accept_rides_and_command_expires_it(): void
    {
        $subscription = DriverSubscription::create([
            'driver_profile_id' => $this->driverProfile->id,
            'subscription_plan_id' => $this->planA->id,
            'amount_gbp' => 10.00,
            'currency' => 'gbp',
            'credits_allocated' => 10,
            'credits_used' => 0,
            'credits_remaining' => 10,
            'starts_at' => now()->subDays(31),
            'expires_at' => now()->subMinute(),
            'status' => 'active',
            'payment_source' => 'wallet',
        ]);

        $ride = Ride::create([
            'rider_id' => $this->riderUser->id,
            'vehicle_type_id' => $this->vehicleType->id,
            'pickup_address' => 'Piccadilly',
            'destination_address' => 'Soho',
            'pickup_latitude' => 51.5,
            'pickup_longitude' => -0.1,
            'destination_latitude' => 51.6,
            'destination_longitude' => -0.2,
            'status' => RideStatus::PENDING,
            'estimated_distance' => 3.0,
            'estimated_duration' => 10,
            'estimated_fare' => 10.00,
        ]);

        $rideRequest = RideRequest::create([
            'ride_id' => $ride->id,
            'driver_profile_id' => $this->driverProfile->id,
            'status' => RideRequestStatus::PENDING,
        ]);

        Sanctum::actingAs($this->driverUser, ['role:driver']);
        $response = $this->postJson("/api/v1/driver/ride-requests/{$rideRequest->id}/accept");
        $response->assertStatus(422);

        // Run expiration command
        $this->artisan('app:expire-subscriptions')->assertExitCode(0);

        $subscription->refresh();
        $this->assertEquals('expired', $subscription->status);
    }
}
