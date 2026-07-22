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
use Carbon\Carbon;
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

    protected function setUp(): void
    {
        parent::setUp();

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

        // Rider User
        $this->riderUser = User::create([
            'name' => 'Rachel Rider',
            'email' => 'rachel.rider@example.com',
            'phone' => '+447911333333',
            'password' => bcrypt('password'),
            'role' => UserRole::RIDER,
            'status' => UserStatus::ACTIVE,
        ]);

        // Subscription Plans (EUR)
        $this->planA = SubscriptionPlan::create([
            'name' => 'Plan A',
            'description' => 'Starter 20 Ride Credits',
            'price_eur' => 10.00,
            'ride_credits' => 20,
            'duration_days' => 30,
            'status' => true,
            'sort_order' => 1,
        ]);

        $this->planB = SubscriptionPlan::create([
            'name' => 'Plan B',
            'description' => 'Pro 50 Ride Credits',
            'price_eur' => 20.00,
            'ride_credits' => 50,
            'duration_days' => 30,
            'status' => true,
            'sort_order' => 2,
        ]);
    }

    /**
     * Test 1: Admin can create, update, and manage Subscription Plans in EUR.
     */
    public function test_admin_can_manage_subscription_plans(): void
    {
        Sanctum::actingAs($this->adminUser, ['role:admin']);

        // Create Plan
        $response = $this->postJson('/api/v1/admin/subscription-plans', [
            'name' => 'Plan C',
            'description' => 'Ultimate 100 Ride Credits',
            'price_eur' => 35.00,
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
                    'price_eur' => 35.00,
                    'currency' => 'EUR',
                    'ride_credits' => 100,
                ],
            ]);

        $planId = $response->json('data.id');

        // Update Plan
        $updateResponse = $this->putJson("/api/v1/admin/subscription-plans/{$planId}", [
            'price_eur' => 30.00,
        ]);
        $updateResponse->assertStatus(200)
            ->assertJson(['data' => ['price_eur' => 30.00]]);

        // Toggle Status
        $statusResponse = $this->patchJson("/api/v1/admin/subscription-plans/{$planId}/status", [
            'active' => false,
        ]);
        $statusResponse->assertStatus(200)
            ->assertJson(['data' => ['status' => false]]);
    }

    /**
     * Test 2: Inactive or deleted plans are hidden from Driver plan listing.
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
     * Test 3: Driver can initiate Stripe subscription purchase in EUR.
     */
    public function test_driver_can_purchase_subscription_via_stripe(): void
    {
        Sanctum::actingAs($this->driverUser, ['role:driver']);

        $response = $this->postJson('/api/v1/driver/subscription/purchase', [
            'subscription_plan_id' => $this->planA->id,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Subscription purchase initiated.',
            ])
            ->assertJsonStructure([
                'data' => ['subscription', 'payment_intent_id', 'client_secret', 'checkout_session_id', 'checkout_url'],
            ]);

        $this->assertDatabaseHas('driver_subscriptions', [
            'driver_profile_id' => $this->driverProfile->id,
            'subscription_plan_id' => $this->planA->id,
            'amount_eur' => 10.00,
            'currency' => 'eur',
            'status' => 'pending',
        ]);
    }

    /**
     * Test 4: Stripe Webhook activates pending subscription and allocates credits idempotently.
     */
    public function test_stripe_webhook_activates_subscription_and_allocates_credits(): void
    {
        Sanctum::actingAs($this->driverUser, ['role:driver']);

        $purchaseResponse = $this->postJson('/api/v1/driver/subscription/purchase', [
            'subscription_plan_id' => $this->planA->id,
        ]);
        $paymentIntentId = $purchaseResponse->json('data.payment_intent_id');
        $subId = $purchaseResponse->json('data.subscription.id');

        // Simulate Stripe Webhook Payload
        $webhookPayload = [
            'id' => 'evt_test_sub_'.uniqid(),
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => $paymentIntentId,
                    'metadata' => [
                        'type' => 'driver_subscription',
                        'driver_subscription_id' => (string) $subId,
                        'driver_profile_id' => (string) $this->driverProfile->id,
                    ],
                ],
            ],
        ];

        // First webhook call
        $response = $this->postJson('/api/v1/stripe/webhook', $webhookPayload);
        $response->assertStatus(200);

        $subscription = DriverSubscription::find($subId);
        $this->assertEquals('active', $subscription->status);
        $this->assertEquals(20, $subscription->credits_allocated);
        $this->assertEquals(20, $subscription->credits_remaining);
        $this->assertEquals(0, $subscription->credits_used);

        // Verify credit ledger transaction created
        $this->assertDatabaseHas('driver_credit_transactions', [
            'driver_profile_id' => $this->driverProfile->id,
            'driver_subscription_id' => $subId,
            'type' => 'subscription_purchase',
            'amount' => 20,
        ]);

        // Duplicate Webhook Call (Idempotency test)
        $response2 = $this->postJson('/api/v1/stripe/webhook', $webhookPayload);
        $response2->assertStatus(200);

        $subscription->refresh();
        $this->assertEquals(20, $subscription->credits_remaining); // Credits not doubled!
    }

    /**
     * Test 5 (OPTION B - Test 1): Driver with 10 credits accepts ride -> 1 credit deducted immediately.
     */
    public function test_option_b_driver_accepts_ride_deducts_1_credit_immediately(): void
    {
        // Give driver active subscription with 10 credits
        $subscription = DriverSubscription::create([
            'driver_profile_id' => $this->driverProfile->id,
            'subscription_plan_id' => $this->planA->id,
            'amount_eur' => 10.00,
            'currency' => 'eur',
            'credits_allocated' => 10,
            'credits_used' => 0,
            'credits_remaining' => 10,
            'starts_at' => now()->subMinute(),
            'expires_at' => now()->addDays(30),
            'status' => 'active',
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

        // Verify credit transaction logged
        $this->assertDatabaseHas('driver_credit_transactions', [
            'driver_profile_id' => $this->driverProfile->id,
            'driver_subscription_id' => $subscription->id,
            'ride_id' => $ride->id,
            'ride_request_id' => $rideRequest->id,
            'type' => 'ride_accept',
            'amount' => -1,
            'balance_before' => 10,
            'balance_after' => 9,
        ]);
    }

    /**
     * Test 6 (OPTION B - Test 2): Rider cancels after acceptance -> NO credit refund.
     */
    public function test_option_b_rider_cancels_after_acceptance_does_not_refund_credit(): void
    {
        $subscription = DriverSubscription::create([
            'driver_profile_id' => $this->driverProfile->id,
            'subscription_plan_id' => $this->planA->id,
            'amount_eur' => 10.00,
            'currency' => 'eur',
            'credits_allocated' => 10,
            'credits_used' => 0,
            'credits_remaining' => 10,
            'starts_at' => now()->subMinute(),
            'expires_at' => now()->addDays(30),
            'status' => 'active',
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

        // Driver accepts -> credit becomes 9
        Sanctum::actingAs($this->driverUser, ['role:driver']);
        $this->postJson("/api/v1/driver/ride-requests/{$rideRequest->id}/accept");

        $subscription->refresh();
        $this->assertEquals(9, $subscription->credits_remaining);

        // Rider cancels ride
        Sanctum::actingAs($this->riderUser, ['role:rider']);
        $cancelResponse = $this->postJson("/api/v1/rides/{$ride->id}/cancel", [
            'reason' => 'Changed my mind',
        ]);

        $cancelResponse->assertStatus(200);

        // Credits MUST REMAIN 9 (No refund!)
        $subscription->refresh();
        $this->assertEquals(9, $subscription->credits_remaining);
        $this->assertEquals(1, $subscription->credits_used);
    }

    /**
     * Test 7 (OPTION B - Test 3): Driver cancels after acceptance -> NO credit refund.
     */
    public function test_option_b_driver_cancels_after_acceptance_does_not_refund_credit(): void
    {
        $subscription = DriverSubscription::create([
            'driver_profile_id' => $this->driverProfile->id,
            'subscription_plan_id' => $this->planA->id,
            'amount_eur' => 10.00,
            'currency' => 'eur',
            'credits_allocated' => 10,
            'credits_used' => 0,
            'credits_remaining' => 10,
            'starts_at' => now()->subMinute(),
            'expires_at' => now()->addDays(30),
            'status' => 'active',
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

        // Driver accepts -> credits becomes 9
        Sanctum::actingAs($this->driverUser, ['role:driver']);
        $this->postJson("/api/v1/driver/ride-requests/{$rideRequest->id}/accept");

        $subscription->refresh();
        $this->assertEquals(9, $subscription->credits_remaining);

        // Driver cancels ride
        $cancelResponse = $this->postJson("/api/v1/rides/{$ride->id}/cancel", [
            'reason' => 'Vehicle breakdown',
        ]);

        $cancelResponse->assertStatus(200);

        // Credits MUST REMAIN 9 (No refund!)
        $subscription->refresh();
        $this->assertEquals(9, $subscription->credits_remaining);
    }

    /**
     * Test 8: Driver with 0 credits or no subscription CANNOT accept a ride request.
     */
    public function test_driver_without_credits_cannot_accept_ride(): void
    {
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

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'You do not have enough ride credits. Please purchase a subscription plan.',
            ]);
    }

    /**
     * Test 9: Expired subscription cannot accept rides and scheduler command marks it expired.
     */
    public function test_expired_subscription_cannot_accept_rides_and_command_expires_it(): void
    {
        $subscription = DriverSubscription::create([
            'driver_profile_id' => $this->driverProfile->id,
            'subscription_plan_id' => $this->planA->id,
            'amount_eur' => 10.00,
            'currency' => 'eur',
            'credits_allocated' => 10,
            'credits_used' => 0,
            'credits_remaining' => 10,
            'starts_at' => now()->subDays(31),
            'expires_at' => now()->subMinute(), // Expired!
            'status' => 'active',
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

        // Run expiration artisan command
        $this->artisan('app:expire-subscriptions')->assertExitCode(0);

        $subscription->refresh();
        $this->assertEquals('expired', $subscription->status);
    }
}
