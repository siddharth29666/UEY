<?php

namespace Tests\Feature;

use App\Enums\RideStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\VehicleStatus;
use App\Models\DriverProfile;
use App\Models\Ride;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use Tests\TestCase;

class RideLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected User $rider;
    protected VehicleType $standardVehicleType;

    protected function setUp(): void
    {
        parent::setUp();

        // Create Rider
        $this->rider = User::create([
            'name' => 'Alice Rider',
            'phone' => '+447911111111',
            'password' => Hash::make('password123'),
            'role' => UserRole::RIDER,
            'status' => UserStatus::ACTIVE,
        ]);

        // Create Vehicle Type
        $this->standardVehicleType = VehicleType::create([
            'name' => 'Standard',
            'capacity' => 4,
            'base_fare' => 5.00,
            'per_km_rate' => 1.50,
            'per_minute_rate' => 0.50,
            'minimum_fare' => 7.00,
            'commission_percentage' => 20.00,
            'icon_url' => 'https://example.com/standard.png',
            'active' => true,
        ]);
    }

    /**
     * Create a mocked driver with profile and vehicle.
     */
    protected function createDriver(string $name, string $phone, bool $online = true): array
    {
        $user = User::create([
            'name' => $name,
            'phone' => $phone,
            'password' => Hash::make('password123'),
            'role' => UserRole::DRIVER,
            'status' => UserStatus::ACTIVE,
        ]);

        $profile = DriverProfile::create([
            'user_id' => $user->id,
            'license_number' => 'DL-' . rand(100000, 999999),
            'license_expiry' => Carbon::now()->addYears(2),
            'is_online' => $online,
            'rating' => 4.9,
            'acceptance_rate' => 98.0,
            'ontime_rate' => 99.0,
            'current_latitude' => 51.5074,
            'current_longitude' => -0.1278,
        ]);

        $vehicle = Vehicle::create([
            'driver_profile_id' => $profile->id,
            'vehicle_type_id' => $this->standardVehicleType->id,
            'make' => 'Toyota',
            'model' => 'Prius',
            'year' => 2021,
            'color' => 'White',
            'plate_number' => 'PL-' . rand(1000, 9999),
            'status' => VehicleStatus::APPROVED,
        ]);

        return compact('user', 'profile', 'vehicle');
    }

    /**
     * Test transition sequence: accepted -> arriving.
     */
    public function test_driver_can_mark_ride_arriving()
    {
        $driver = $this->createDriver('Bob Driver', '+447922222222');
        $token = $driver['user']->createToken('test-token', ['role:driver'])->plainTextToken;

        $ride = Ride::create([
            'rider_id' => $this->rider->id,
            'driver_profile_id' => $driver['profile']->id,
            'vehicle_type_id' => $this->standardVehicleType->id,
            'pickup_address' => 'London Eye',
            'pickup_latitude' => 51.5074,
            'pickup_longitude' => -0.1278,
            'destination_address' => 'Regent Park',
            'destination_latitude' => 51.5204,
            'destination_longitude' => -0.1482,
            'status' => RideStatus::ACCEPTED,
            'otp' => '123456',
            'estimated_distance' => 2.0,
            'estimated_duration' => 5,
            'estimated_fare' => 10.0,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson("/api/v1/driver/rides/{$ride->id}/arriving");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Ride status updated to arriving.',
            ]);

        $this->assertEquals(RideStatus::ARRIVING, $ride->fresh()->status);
    }

    /**
     * Test transition sequence: arriving -> arrived.
     */
    public function test_driver_can_mark_ride_arrived()
    {
        $driver = $this->createDriver('Bob Driver', '+447922222222');
        $token = $driver['user']->createToken('test-token', ['role:driver'])->plainTextToken;

        $ride = Ride::create([
            'rider_id' => $this->rider->id,
            'driver_profile_id' => $driver['profile']->id,
            'vehicle_type_id' => $this->standardVehicleType->id,
            'pickup_address' => 'London Eye',
            'pickup_latitude' => 51.5074,
            'pickup_longitude' => -0.1278,
            'destination_address' => 'Regent Park',
            'destination_latitude' => 51.5204,
            'destination_longitude' => -0.1482,
            'status' => RideStatus::ARRIVING,
            'otp' => '123456',
            'estimated_distance' => 2.0,
            'estimated_duration' => 5,
            'estimated_fare' => 10.0,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson("/api/v1/driver/rides/{$ride->id}/arrived");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Ride status updated to arrived.',
            ]);

        $ride->refresh();
        $this->assertEquals(RideStatus::ARRIVED, $ride->status);
        $this->assertNotNull($ride->arrived_at);
    }

    /**
     * Test starting a ride fails with an invalid OTP.
     */
    public function test_driver_cannot_start_ride_with_invalid_otp()
    {
        $driver = $this->createDriver('Bob Driver', '+447922222222');
        $token = $driver['user']->createToken('test-token', ['role:driver'])->plainTextToken;

        $ride = Ride::create([
            'rider_id' => $this->rider->id,
            'driver_profile_id' => $driver['profile']->id,
            'vehicle_type_id' => $this->standardVehicleType->id,
            'pickup_address' => 'London Eye',
            'pickup_latitude' => 51.5074,
            'pickup_longitude' => -0.1278,
            'destination_address' => 'Regent Park',
            'destination_latitude' => 51.5204,
            'destination_longitude' => -0.1482,
            'status' => RideStatus::ARRIVED,
            'otp' => '123456',
            'estimated_distance' => 2.0,
            'estimated_duration' => 5,
            'estimated_fare' => 10.0,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson("/api/v1/driver/rides/{$ride->id}/start", [
            'otp' => '654321', // Wrong OTP
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['otp']);

        $this->assertEquals(RideStatus::ARRIVED, $ride->fresh()->status);
    }

    /**
     * Test starting a ride successfully with valid OTP.
     */
    public function test_driver_can_start_ride_with_valid_otp()
    {
        $driver = $this->createDriver('Bob Driver', '+447922222222');
        $token = $driver['user']->createToken('test-token', ['role:driver'])->plainTextToken;

        $ride = Ride::create([
            'rider_id' => $this->rider->id,
            'driver_profile_id' => $driver['profile']->id,
            'vehicle_type_id' => $this->standardVehicleType->id,
            'pickup_address' => 'London Eye',
            'pickup_latitude' => 51.5074,
            'pickup_longitude' => -0.1278,
            'destination_address' => 'Regent Park',
            'destination_latitude' => 51.5204,
            'destination_longitude' => -0.1482,
            'status' => RideStatus::ARRIVED,
            'otp' => '123456',
            'estimated_distance' => 2.0,
            'estimated_duration' => 5,
            'estimated_fare' => 10.0,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson("/api/v1/driver/rides/{$ride->id}/start", [
            'otp' => '123456',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Ride started successfully.',
            ]);

        $ride->refresh();
        $this->assertEquals(RideStatus::IN_PROGRESS, $ride->status);
        $this->assertNotNull($ride->started_at);
        $this->assertNotNull($ride->otp_verified_at);
        $this->assertEquals($driver['user']->id, $ride->otp_verified_by);
    }

    /**
     * Test completing a ride: calculations and breakdowns.
     */
    public function test_driver_can_complete_ride()
    {
        $driver = $this->createDriver('Bob Driver', '+447922222222');
        $token = $driver['user']->createToken('test-token', ['role:driver'])->plainTextToken;

        $ride = Ride::create([
            'rider_id' => $this->rider->id,
            'driver_profile_id' => $driver['profile']->id,
            'vehicle_type_id' => $this->standardVehicleType->id,
            'pickup_address' => 'London Eye',
            'pickup_latitude' => 51.5074,
            'pickup_longitude' => -0.1278,
            'destination_address' => 'Regent Park',
            'destination_latitude' => 51.5204,
            'destination_longitude' => -0.1482,
            'status' => RideStatus::IN_PROGRESS,
            'otp' => '123456',
            'estimated_distance' => 2.0,
            'estimated_duration' => 5,
            'estimated_fare' => 10.0,
        ]);

        // actual_distance: 3.5 km, actual_duration: 10 mins
        // standard rates: base_fare=5.00, per_km_rate=1.50, per_minute_rate=0.50
        // expected: 5.00 + (1.50 * 3.5) + (0.50 * 10) = 5.00 + 5.25 + 5.00 = 15.25
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson("/api/v1/driver/rides/{$ride->id}/complete", [
            'actual_distance' => 3.5,
            'actual_duration' => 10,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Ride completed successfully.',
            ]);

        $ride->refresh();
        $this->assertEquals(RideStatus::COMPLETED, $ride->status);
        $this->assertNotNull($ride->completed_at);
        $this->assertEquals(3.5, (float) $ride->actual_distance);
        $this->assertEquals(10, $ride->actual_duration);
        $this->assertEquals(15.25, (float) $ride->actual_fare);

        // Check fare breakdown JSON
        $breakdown = $ride->fare_breakdown;
        $this->assertEquals(5.00, $breakdown['base_fare']);
        $this->assertEquals(5.25, $breakdown['distance_fare']);
        $this->assertEquals(5.00, $breakdown['duration_fare']);
        $this->assertEquals(15.25, $breakdown['final_fare']);
        $this->assertFalse($breakdown['applied_minimum_fare']);

        // Check driver profile location updated to destination coordinates
        $driverProfile = $driver['profile']->fresh();
        $this->assertEquals(51.5204, (float) $driverProfile->current_latitude);
        $this->assertEquals(-0.1482, (float) $driverProfile->current_longitude);
    }

    /**
     * Test completing a ride triggers minimum fare capping.
     */
    public function test_driver_can_complete_ride_with_minimum_fare_cap()
    {
        $driver = $this->createDriver('Bob Driver', '+447922222222');
        $token = $driver['user']->createToken('test-token', ['role:driver'])->plainTextToken;

        $ride = Ride::create([
            'rider_id' => $this->rider->id,
            'driver_profile_id' => $driver['profile']->id,
            'vehicle_type_id' => $this->standardVehicleType->id,
            'pickup_address' => 'London Eye',
            'pickup_latitude' => 51.5074,
            'pickup_longitude' => -0.1278,
            'destination_address' => 'Regent Park',
            'destination_latitude' => 51.5204,
            'destination_longitude' => -0.1482,
            'status' => RideStatus::IN_PROGRESS,
            'otp' => '123456',
            'estimated_distance' => 2.0,
            'estimated_duration' => 5,
            'estimated_fare' => 10.0,
        ]);

        // actual_distance: 0.5 km, actual_duration: 2 mins
        // standard rates: base_fare=5.00, per_km_rate=1.50, per_minute_rate=0.50, minimum_fare=7.00
        // expected calculated: 5.00 + (1.50 * 0.5) + (0.50 * 2) = 5.00 + 0.75 + 1.00 = 6.75
        // expected final (capped): 7.00
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson("/api/v1/driver/rides/{$ride->id}/complete", [
            'actual_distance' => 0.5,
            'actual_duration' => 2,
        ]);

        $response->assertStatus(200);

        $ride->refresh();
        $this->assertEquals(7.00, (float) $ride->actual_fare);
        $this->assertTrue($ride->fare_breakdown['applied_minimum_fare']);
    }

    /**
     * Test invalid transitions are blocked.
     */
    public function test_invalid_status_transitions_fail()
    {
        $driver = $this->createDriver('Bob Driver', '+447922222222');
        $token = $driver['user']->createToken('test-token', ['role:driver'])->plainTextToken;

        $ride = Ride::create([
            'rider_id' => $this->rider->id,
            'driver_profile_id' => $driver['profile']->id,
            'vehicle_type_id' => $this->standardVehicleType->id,
            'pickup_address' => 'London Eye',
            'pickup_latitude' => 51.5074,
            'pickup_longitude' => -0.1278,
            'destination_address' => 'Regent Park',
            'destination_latitude' => 51.5204,
            'destination_longitude' => -0.1482,
            'status' => RideStatus::ACCEPTED,
            'otp' => '123456',
            'estimated_distance' => 2.0,
            'estimated_duration' => 5,
            'estimated_fare' => 10.0,
        ]);

        // Attempting to skip arriving/arrived and start ride directly
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson("/api/v1/driver/rides/{$ride->id}/start", [
            'otp' => '123456',
        ]);

        $response->assertStatus(422);
        $this->assertEquals(RideStatus::ACCEPTED, $ride->fresh()->status);
    }

    /**
     * Test unassigned driver cannot update the ride.
     */
    public function test_unassigned_driver_cannot_update_ride()
    {
        $driver1 = $this->createDriver('Bob Driver', '+447922222222');
        $driver2 = $this->createDriver('Charlie Driver', '+447933333333');
        $token2 = $driver2['user']->createToken('test-token', ['role:driver'])->plainTextToken;

        $ride = Ride::create([
            'rider_id' => $this->rider->id,
            'driver_profile_id' => $driver1['profile']->id, // Assigned to driver1
            'vehicle_type_id' => $this->standardVehicleType->id,
            'pickup_address' => 'London Eye',
            'pickup_latitude' => 51.5074,
            'pickup_longitude' => -0.1278,
            'destination_address' => 'Regent Park',
            'destination_latitude' => 51.5204,
            'destination_longitude' => -0.1482,
            'status' => RideStatus::ACCEPTED,
            'otp' => '123456',
            'estimated_distance' => 2.0,
            'estimated_duration' => 5,
            'estimated_fare' => 10.0,
        ]);

        // driver2 attempts to update the ride status
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token2,
        ])->postJson("/api/v1/driver/rides/{$ride->id}/arriving");

        $response->assertStatus(403);
        $this->assertEquals(RideStatus::ACCEPTED, $ride->fresh()->status);
    }
}
