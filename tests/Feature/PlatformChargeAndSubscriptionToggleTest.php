<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RideRequestStatus;
use App\Enums\RideStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\DriverProfile;
use App\Models\DriverSubscription;
use App\Models\Payment;
use App\Models\Ride;
use App\Models\RideRequest;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\VehicleType;
use App\Models\Wallet;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlatformChargeAndSubscriptionToggleTest extends TestCase
{
    use RefreshDatabase;

    protected User $driverUser;
    protected DriverProfile $driverProfile;
    protected User $riderUser;
    protected VehicleType $vehicleType;
    protected Wallet $driverWallet;
    protected Wallet $riderWallet;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vehicleType = VehicleType::create([
            'name' => 'Sedan',
            'capacity' => 4,
            'base_fare' => 5.00,
            'per_km_rate' => 1.50,
            'per_minute_rate' => 0.20,
            'minimum_fare' => 8.00,
            'commission_percentage' => 10.00,
        ]);

        $this->driverUser = User::create([
            'name' => 'Dan Driver',
            'email' => 'dan.driver@example.com',
            'phone' => '+447911222333',
            'password' => bcrypt('password'),
            'role' => UserRole::DRIVER,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->driverProfile = DriverProfile::create([
            'user_id' => $this->driverUser->id,
            'vehicle_type_id' => $this->vehicleType->id,
            'is_online' => true,
            'is_available' => true,
        ]);

        $this->driverWallet = Wallet::create([
            'user_id' => $this->driverUser->id,
            'balance' => 0.00,
            'currency' => 'GBP',
            'status' => 'active',
        ]);

        $this->riderUser = User::create([
            'name' => 'Rita Rider',
            'email' => 'rita.rider@example.com',
            'phone' => '+447911444555',
            'password' => bcrypt('password'),
            'role' => UserRole::RIDER,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->riderWallet = Wallet::create([
            'user_id' => $this->riderUser->id,
            'balance' => 200.00,
            'currency' => 'GBP',
            'status' => 'active',
        ]);
    }

    /**
     * Test 1: Driver WITHOUT subscription can accept a ride when DRIVER_SUBSCRIPTION_ENABLED=false.
     */
    public function test_driver_without_subscription_can_accept_ride_when_feature_disabled(): void
    {
        config(['app.driver_subscription_enabled' => false]);

        $ride = Ride::create([
            'rider_id' => $this->riderUser->id,
            'vehicle_type_id' => $this->vehicleType->id,
            'pickup_address' => 'London Bridge',
            'destination_address' => 'Tower Bridge',
            'pickup_latitude' => 51.5074,
            'pickup_longitude' => -0.1278,
            'destination_latitude' => 51.5055,
            'destination_longitude' => -0.0754,
            'status' => RideStatus::PENDING,
            'estimated_distance' => 2.5,
            'estimated_duration' => 8,
            'estimated_fare' => 15.00,
            'payment_method' => PaymentMethod::CASH,
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
                'message' => 'Ride request accepted successfully.',
            ]);

        $ride->refresh();
        $this->assertEquals(RideStatus::ACCEPTED, $ride->status);
        $this->assertEquals($this->driverProfile->id, $ride->driver_profile_id);
    }

    /**
     * Test 2: Driver with 0 credits can accept a ride when DRIVER_SUBSCRIPTION_ENABLED=false.
     */
    public function test_driver_with_zero_credits_can_accept_ride_when_feature_disabled(): void
    {
        config(['app.driver_subscription_enabled' => false]);

        $plan = SubscriptionPlan::create([
            'name' => 'Plan A',
            'description' => 'Test Plan',
            'price_gbp' => 10.00,
            'ride_credits' => 10,
            'duration_days' => 30,
            'status' => true,
        ]);

        DriverSubscription::create([
            'driver_profile_id' => $this->driverProfile->id,
            'subscription_plan_id' => $plan->id,
            'amount_gbp' => 10.00,
            'currency' => 'gbp',
            'credits_allocated' => 10,
            'credits_used' => 10,
            'credits_remaining' => 0,
            'starts_at' => now()->subMinute(),
            'expires_at' => now()->addDays(30),
            'status' => 'active',
            'payment_source' => 'wallet',
        ]);

        $ride = Ride::create([
            'rider_id' => $this->riderUser->id,
            'vehicle_type_id' => $this->vehicleType->id,
            'pickup_address' => 'Oxford Circus',
            'destination_address' => 'Regent Street',
            'pickup_latitude' => 51.515,
            'pickup_longitude' => -0.141,
            'destination_latitude' => 51.513,
            'destination_longitude' => -0.139,
            'status' => RideStatus::PENDING,
            'estimated_distance' => 1.5,
            'estimated_duration' => 5,
            'estimated_fare' => 10.00,
            'payment_method' => PaymentMethod::CASH,
        ]);

        $rideRequest = RideRequest::create([
            'ride_id' => $ride->id,
            'driver_profile_id' => $this->driverProfile->id,
            'status' => RideRequestStatus::PENDING,
        ]);

        Sanctum::actingAs($this->driverUser, ['role:driver']);

        $response = $this->postJson("/api/v1/driver/ride-requests/{$rideRequest->id}/accept");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    /**
     * Test 3: 10% Platform Charge calculation on £100 booking (£10 platform commission, £90 driver earning).
     */
    public function test_10_percent_platform_charge_calculation(): void
    {
        $ride = Ride::create([
            'rider_id' => $this->riderUser->id,
            'driver_profile_id' => $this->driverProfile->id,
            'vehicle_type_id' => $this->vehicleType->id,
            'pickup_address' => 'Heathrow',
            'destination_address' => 'Central London',
            'pickup_latitude' => 51.47,
            'pickup_longitude' => -0.45,
            'destination_latitude' => 51.50,
            'destination_longitude' => -0.12,
            'status' => RideStatus::COMPLETED,
            'actual_fare' => 100.00,
            'actual_discount_amount' => 0.00,
            'final_actual_fare' => 100.00,
            'payment_method' => PaymentMethod::WALLET,
        ]);

        $paymentService = app(PaymentService::class);
        $payment = $paymentService->processPayment($ride);

        $this->assertEquals(10.00, (float) $payment->platform_commission);
        $this->assertEquals(90.00, (float) $payment->driver_earning);
        $this->assertEquals(100.00, (float) $payment->total);
    }

    /**
     * Test 4: Cash booking debits 10% platform charge (£10) from driver's wallet.
     */
    public function test_cash_booking_debits_10_percent_platform_charge_from_driver_wallet(): void
    {
        $ride = Ride::create([
            'rider_id' => $this->riderUser->id,
            'driver_profile_id' => $this->driverProfile->id,
            'vehicle_type_id' => $this->vehicleType->id,
            'pickup_address' => 'Victoria',
            'destination_address' => 'Chelsea',
            'pickup_latitude' => 51.49,
            'pickup_longitude' => -0.14,
            'destination_latitude' => 51.48,
            'destination_longitude' => -0.16,
            'status' => RideStatus::COMPLETED,
            'actual_fare' => 100.00,
            'actual_discount_amount' => 0.00,
            'final_actual_fare' => 100.00,
            'payment_method' => PaymentMethod::CASH,
        ]);

        $paymentService = app(PaymentService::class);
        $payment = $paymentService->processPayment($ride);

        $this->assertEquals(PaymentStatus::PAID, $payment->payment_status);

        // Driver wallet debited £10
        $this->driverWallet->refresh();
        $this->assertEquals(-10.00, (float) $this->driverWallet->balance);
    }

    /**
     * Test 5: Wallet/Online booking credits 90% (£90) to driver's wallet.
     */
    public function test_wallet_booking_credits_90_percent_earning_to_driver_wallet(): void
    {
        $ride = Ride::create([
            'rider_id' => $this->riderUser->id,
            'driver_profile_id' => $this->driverProfile->id,
            'vehicle_type_id' => $this->vehicleType->id,
            'pickup_address' => 'Euston',
            'destination_address' => 'Camden',
            'pickup_latitude' => 51.52,
            'pickup_longitude' => -0.13,
            'destination_latitude' => 51.54,
            'destination_longitude' => -0.14,
            'status' => RideStatus::COMPLETED,
            'actual_fare' => 100.00,
            'actual_discount_amount' => 0.00,
            'final_actual_fare' => 100.00,
            'payment_method' => PaymentMethod::WALLET,
        ]);

        $paymentService = app(PaymentService::class);
        $payment = $paymentService->processPayment($ride);

        $this->assertEquals(PaymentStatus::PAID, $payment->payment_status);

        // Rider wallet debited £100
        $this->riderWallet->refresh();
        $this->assertEquals(100.00, (float) $this->riderWallet->balance);

        // Driver wallet credited £90
        $this->driverWallet->refresh();
        $this->assertEquals(90.00, (float) $this->driverWallet->balance);
    }

    /**
     * Test 6: Setting DRIVER_SUBSCRIPTION_ENABLED=true re-enables credit deduction requirement.
     */
    public function test_enabling_subscription_feature_flag_restores_credit_deduction(): void
    {
        config(['app.driver_subscription_enabled' => true]);

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

        // Fails with HTTP 422 because driver has no subscription and feature flag is true
        $response = $this->postJson("/api/v1/driver/ride-requests/{$rideRequest->id}/accept");

        $response->assertStatus(422);
    }
}
