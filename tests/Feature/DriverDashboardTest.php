<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Enums\RideRequestStatus;
use App\Enums\RideStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\DriverProfile;
use App\Models\Payment;
use App\Models\Ride;
use App\Models\RideRequest;
use App\Models\RideReview;
use App\Models\User;
use App\Models\VehicleType;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DriverDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected User $driverUser;
    protected DriverProfile $driverProfile;
    protected User $riderUser;
    protected VehicleType $vehicleType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->driverUser = User::create([
            'name' => 'Bob Driver',
            'email' => 'bob.driver@example.com',
            'phone' => '+447911999999',
            'password' => bcrypt('password'),
            'role' => UserRole::DRIVER,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->driverProfile = DriverProfile::create([
            'user_id' => $this->driverUser->id,
            'license_number' => 'DL-999888',
            'license_expiry' => '2027-06-21',
            'is_online' => true,
            'rating' => 5.00,
            'acceptance_rate' => 100.00,
            'ontime_rate' => 100.00,
        ]);

        $this->riderUser = User::create([
            'name' => 'John Rider',
            'email' => 'john.rider@example.com',
            'phone' => '+447911123456',
            'password' => bcrypt('password'),
            'role' => UserRole::RIDER,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->vehicleType = VehicleType::create([
            'name' => 'Economy',
            'capacity' => 4,
            'base_fare' => 2.50,
            'per_km_rate' => 1.20,
            'per_minute_rate' => 0.30,
            'minimum_fare' => 5.00,
            'commission_percentage' => 15.00,
            'active' => true,
        ]);
    }

    public function test_unauthenticated_driver_cannot_access_dashboard()
    {
        $this->getJson('/api/v1/driver/dashboard')->assertStatus(401);
    }

    public function test_driver_with_zero_rides_returns_default_stats()
    {
        Sanctum::actingAs($this->driverUser, ['role:driver']);

        $response = $this->getJson('/api/v1/driver/dashboard');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'dashboard' => [
                    'driver_profile_id' => $this->driverProfile->id,
                    'is_online' => true,
                    'rating' => 5.00,
                    'acceptance_rate' => 100.00,
                    'ontime_rate' => 100.00,
                    'completed_rides_count' => 0,
                    'earnings_summary' => [
                        'today' => 0.00,
                        'this_week' => 0.00,
                        'total' => 0.00,
                    ],
                ],
            ]);
    }

    public function test_completed_rides_count_does_not_count_cancelled_or_pending_rides()
    {
        Sanctum::actingAs($this->driverUser, ['role:driver']);

        // 1. Completed Ride
        Ride::create([
            'rider_id' => $this->riderUser->id,
            'driver_profile_id' => $this->driverProfile->id,
            'vehicle_type_id' => $this->vehicleType->id,
            'pickup_address' => 'A',
            'destination_address' => 'B',
            'pickup_latitude' => 51.5,
            'pickup_longitude' => -0.1,
            'destination_latitude' => 51.6,
            'destination_longitude' => -0.2,
            'estimated_distance' => 10.0,
            'estimated_duration' => 15,
            'estimated_fare' => 15.00,
            'status' => RideStatus::COMPLETED,
            'otp' => '123456',
        ]);

        // 2. Cancelled Ride
        Ride::create([
            'rider_id' => $this->riderUser->id,
            'driver_profile_id' => $this->driverProfile->id,
            'vehicle_type_id' => $this->vehicleType->id,
            'pickup_address' => 'A',
            'destination_address' => 'C',
            'pickup_latitude' => 51.5,
            'pickup_longitude' => -0.1,
            'destination_latitude' => 51.7,
            'destination_longitude' => -0.3,
            'estimated_distance' => 12.0,
            'estimated_duration' => 18,
            'estimated_fare' => 18.00,
            'status' => RideStatus::CANCELLED,
            'otp' => '654321',
        ]);

        // 3. Accepted (In-progress) Ride
        Ride::create([
            'rider_id' => $this->riderUser->id,
            'driver_profile_id' => $this->driverProfile->id,
            'vehicle_type_id' => $this->vehicleType->id,
            'pickup_address' => 'A',
            'destination_address' => 'D',
            'pickup_latitude' => 51.5,
            'pickup_longitude' => -0.1,
            'destination_latitude' => 51.8,
            'destination_longitude' => -0.4,
            'estimated_distance' => 8.0,
            'estimated_duration' => 12,
            'estimated_fare' => 12.00,
            'status' => RideStatus::ACCEPTED,
            'otp' => '111111',
        ]);

        $response = $this->getJson('/api/v1/driver/dashboard');

        $response->assertStatus(200)
            ->assertJsonPath('dashboard.completed_rides_count', 1);
    }

    public function test_earnings_summary_only_calculates_paid_payments()
    {
        Sanctum::actingAs($this->driverUser, ['role:driver']);

        $ride1 = Ride::create([
            'rider_id' => $this->riderUser->id,
            'driver_profile_id' => $this->driverProfile->id,
            'vehicle_type_id' => $this->vehicleType->id,
            'pickup_address' => 'A',
            'destination_address' => 'B',
            'pickup_latitude' => 51.5,
            'pickup_longitude' => -0.1,
            'destination_latitude' => 51.6,
            'destination_longitude' => -0.2,
            'estimated_distance' => 10.0,
            'estimated_duration' => 15,
            'estimated_fare' => 15.00,
            'status' => RideStatus::COMPLETED,
            'otp' => '123456',
        ]);

        $ride2 = Ride::create([
            'rider_id' => $this->riderUser->id,
            'driver_profile_id' => $this->driverProfile->id,
            'vehicle_type_id' => $this->vehicleType->id,
            'pickup_address' => 'A',
            'destination_address' => 'C',
            'pickup_latitude' => 51.5,
            'pickup_longitude' => -0.1,
            'destination_latitude' => 51.7,
            'destination_longitude' => -0.3,
            'estimated_distance' => 12.0,
            'estimated_duration' => 18,
            'estimated_fare' => 18.00,
            'status' => RideStatus::COMPLETED,
            'otp' => '654321',
        ]);

        // Paid payment
        Payment::create([
            'ride_id' => $ride1->id,
            'rider_id' => $this->riderUser->id,
            'driver_profile_id' => $this->driverProfile->id,
            'payment_method' => 'cash',
            'payment_status' => PaymentStatus::PAID,
            'subtotal' => 15.00,
            'tax' => 0.00,
            'discount' => 0.00,
            'platform_commission' => 2.25,
            'driver_earning' => 12.75,
            'total' => 15.00,
            'paid_at' => Carbon::now(),
        ]);

        // Unpaid/Pending payment (should be excluded)
        Payment::create([
            'ride_id' => $ride2->id,
            'rider_id' => $this->riderUser->id,
            'driver_profile_id' => $this->driverProfile->id,
            'payment_method' => 'cash',
            'payment_status' => PaymentStatus::PENDING,
            'subtotal' => 18.00,
            'tax' => 0.00,
            'discount' => 0.00,
            'platform_commission' => 2.70,
            'driver_earning' => 15.30,
            'total' => 18.00,
        ]);

        $response = $this->getJson('/api/v1/driver/dashboard');

        $response->assertStatus(200)
            ->assertJsonPath('dashboard.earnings_summary.total', 12.75);
    }

    public function test_earnings_summary_timezone_boundaries()
    {
        Sanctum::actingAs($this->driverUser, ['role:driver']);

        $ride = Ride::create([
            'rider_id' => $this->riderUser->id,
            'driver_profile_id' => $this->driverProfile->id,
            'vehicle_type_id' => $this->vehicleType->id,
            'pickup_address' => 'A',
            'destination_address' => 'B',
            'pickup_latitude' => 51.5,
            'pickup_longitude' => -0.1,
            'destination_latitude' => 51.6,
            'destination_longitude' => -0.2,
            'estimated_distance' => 10.0,
            'estimated_duration' => 15,
            'estimated_fare' => 15.00,
            'status' => RideStatus::COMPLETED,
            'otp' => '123456',
        ]);

        // Let's assume driver local timezone is GMT+2 (e.g. Europe/Paris)
        // If it is 2026-07-21 00:30:00 in GMT+2, then in UTC it is 2026-07-20 22:30:00.
        // Today in Paris is July 21st, but in UTC it is July 20th.
        
        $localTime = Carbon::create(2026, 7, 21, 0, 30, 0, 'Europe/Paris');
        $utcTime = $localTime->copy()->setTimezone('UTC'); // 2026-07-20 22:30:00 UTC

        Payment::create([
            'ride_id' => $ride->id,
            'rider_id' => $this->riderUser->id,
            'driver_profile_id' => $this->driverProfile->id,
            'payment_method' => 'cash',
            'payment_status' => PaymentStatus::PAID,
            'subtotal' => 20.00,
            'tax' => 0.00,
            'discount' => 0.00,
            'platform_commission' => 3.00,
            'driver_earning' => 17.00,
            'total' => 20.00,
            'paid_at' => $utcTime,
        ]);

        // 1. Fetch dashboard with local timezone Europe/Paris
        Carbon::setTestNow($localTime);

        $response = $this->withHeaders(['X-Timezone' => 'Europe/Paris'])
            ->getJson('/api/v1/driver/dashboard');

        $response->assertStatus(200)
            ->assertJsonPath('dashboard.earnings_summary.today', 17.00);

        // 2. Fetch dashboard with UTC timezone (it should think the payment was yesterday, so today's earning is 0.00)
        $responseUtc = $this->withHeaders(['X-Timezone' => 'UTC'])
            ->getJson('/api/v1/driver/dashboard');

        $responseUtc->assertStatus(200)
            ->assertJsonPath('dashboard.earnings_summary.today', 0.00);

        Carbon::setTestNow(); // Reset test time
    }

    public function test_dynamic_acceptance_rate_calculation()
    {
        Sanctum::actingAs($this->driverUser, ['role:driver']);

        // Create 4 ride requests (offers)
        // 3 Accepted, 1 Declined
        for ($i = 1; $i <= 4; $i++) {
            RideRequest::create([
                'ride_id' => 100 + $i,
                'driver_profile_id' => $this->driverProfile->id,
                'status' => $i === 4 ? RideRequestStatus::DECLINED : RideRequestStatus::ACCEPTED,
                'expires_at' => Carbon::now()->addMinutes(5),
            ]);
        }

        $response = $this->getJson('/api/v1/driver/dashboard');

        // 3/4 = 75.00%
        $response->assertStatus(200)
            ->assertJsonPath('dashboard.acceptance_rate', 75.00);
    }

    public function test_dynamic_ontime_rate_calculation()
    {
        Sanctum::actingAs($this->driverUser, ['role:driver']);

        // Create completed rides with arrived_at and started_at timestamps
        // Ride 1: Started 2 mins after arrived -> On Time
        Ride::create([
            'rider_id' => $this->riderUser->id,
            'driver_profile_id' => $this->driverProfile->id,
            'vehicle_type_id' => $this->vehicleType->id,
            'pickup_address' => 'A',
            'destination_address' => 'B',
            'pickup_latitude' => 51.5,
            'pickup_longitude' => -0.1,
            'destination_latitude' => 51.6,
            'destination_longitude' => -0.2,
            'estimated_distance' => 10.0,
            'estimated_duration' => 15,
            'estimated_fare' => 15.00,
            'status' => RideStatus::COMPLETED,
            'otp' => '123456',
            'arrived_at' => Carbon::now()->subMinutes(10),
            'started_at' => Carbon::now()->subMinutes(8), // 2 mins diff (<= 5)
        ]);

        // Ride 2: Started 7 mins after arrived -> Late
        Ride::create([
            'rider_id' => $this->riderUser->id,
            'driver_profile_id' => $this->driverProfile->id,
            'vehicle_type_id' => $this->vehicleType->id,
            'pickup_address' => 'A',
            'destination_address' => 'C',
            'pickup_latitude' => 51.5,
            'pickup_longitude' => -0.1,
            'destination_latitude' => 51.7,
            'destination_longitude' => -0.3,
            'estimated_distance' => 12.0,
            'estimated_duration' => 18,
            'estimated_fare' => 18.00,
            'status' => RideStatus::COMPLETED,
            'otp' => '654321',
            'arrived_at' => Carbon::now()->subMinutes(20),
            'started_at' => Carbon::now()->subMinutes(13), // 7 mins diff (> 5)
        ]);

        $response = $this->getJson('/api/v1/driver/dashboard');

        // 1/2 = 50.00%
        $response->assertStatus(200)
            ->assertJsonPath('dashboard.ontime_rate', 50.00);
    }

    public function test_rating_recalculation_on_review_events()
    {
        // 1. Submit first review (rating = 4)
        $review1 = RideReview::create([
            'ride_id' => 1,
            'reviewer_id' => $this->riderUser->id,
            'reviewee_id' => $this->driverUser->id,
            'rating' => 4,
            'review' => 'Good ride.',
        ]);

        $this->assertEquals(4.00, (float) $this->driverProfile->fresh()->rating);
        $this->assertEquals(1, $this->driverProfile->fresh()->total_reviews);

        // 2. Submit second review (rating = 5)
        $review2 = RideReview::create([
            'ride_id' => 2,
            'reviewer_id' => $this->riderUser->id,
            'reviewee_id' => $this->driverUser->id,
            'rating' => 5,
            'review' => 'Excellent.',
        ]);

        // (4 + 5) / 2 = 4.50
        $this->assertEquals(4.50, (float) $this->driverProfile->fresh()->rating);
        $this->assertEquals(2, $this->driverProfile->fresh()->total_reviews);

        // 3. Update rating of review 1 to 5
        $review1->update(['rating' => 5]);

        // (5 + 5) / 2 = 5.00
        $this->assertEquals(5.00, (float) $this->driverProfile->fresh()->rating);
        $this->assertEquals(2, $this->driverProfile->fresh()->total_reviews);

        // 4. Soft delete review 2
        $review2->delete();

        // only review 1 remains (rating = 5)
        $this->assertEquals(5.00, (float) $this->driverProfile->fresh()->rating);
        $this->assertEquals(1, $this->driverProfile->fresh()->total_reviews);

        // 5. Restore review 2
        $review2->restore();

        // (5 + 5) / 2 = 5.00
        $this->assertEquals(5.00, (float) $this->driverProfile->fresh()->rating);
        $this->assertEquals(2, $this->driverProfile->fresh()->total_reviews);
    }
}
