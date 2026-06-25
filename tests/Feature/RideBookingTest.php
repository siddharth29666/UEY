<?php

namespace Tests\Feature;

use App\Enums\RideRequestStatus;
use App\Enums\RideStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\VehicleStatus;
use App\Models\DriverProfile;
use App\Models\Ride;
use App\Models\RideRequest;
use App\Models\RideStatusLog;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redis;
use Carbon\Carbon;
use Tests\TestCase;

class RideBookingTest extends TestCase
{
    use RefreshDatabase;

    protected User $rider;
    protected VehicleType $standardVehicleType;
    protected VehicleType $suvVehicleType;

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

        // Create Vehicle Types
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

        $this->suvVehicleType = VehicleType::create([
            'name' => 'SUV',
            'capacity' => 6,
            'base_fare' => 10.00,
            'per_km_rate' => 2.50,
            'per_minute_rate' => 1.00,
            'minimum_fare' => 15.00,
            'commission_percentage' => 20.00,
            'icon_url' => 'https://example.com/suv.png',
            'active' => true,
        ]);
    }

    /**
     * Create a mocked driver with profile and vehicle.
     */
    protected function createDriver(string $name, string $phone, VehicleType $vehicleType, bool $online = true): array
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
            'vehicle_type_id' => $vehicleType->id,
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
     * Test fare estimation endpoint.
     */
    public function test_rider_can_estimate_fares()
    {
        $token = $this->rider->createToken('test-token', ['role:rider'])->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/rides/estimate', [
            'pickup_latitude' => 51.5074,
            'pickup_longitude' => -0.1278,
            'destination_latitude' => 51.5204,
            'destination_longitude' => -0.1482,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'estimates' => [
                    '*' => [
                        'vehicle_type_id',
                        'name',
                        'capacity',
                        'estimated_distance',
                        'estimated_duration',
                        'estimated_fare',
                    ]
                ]
            ]);

        $estimates = $response->json('estimates');
        $this->assertCount(2, $estimates);

        // Distance is ~1.99 KM. Duration ~3 mins. Standard: 5.00 + (1.50*1.99) + (0.50*3) = ~9.48. SUV: 10.00 + (2.50*1.99) + (1.00*3) = ~17.97.
        $this->assertEquals('Standard', $estimates[0]['name']);
        $this->assertGreaterThan(7.00, $estimates[0]['estimated_fare']);
    }

    /**
     * Test ride request creation and automatic driver matching.
     */
    public function test_rider_can_request_ride_and_matches_drivers()
    {
        // Create the two drivers first to get valid auto-incremented IDs
        $driver1 = $this->createDriver('Driver One', '+447911222222', $this->standardVehicleType);
        $driver2 = $this->createDriver('Driver Two', '+447911333333', $this->standardVehicleType);

        $id1 = (string) $driver1['profile']->id;
        $id2 = (string) $driver2['profile']->id;

        // Mock Redis search for nearby drivers using the actual generated IDs
        Redis::shouldReceive('executeRaw')
            ->once()
            ->with(\Mockery::type('array'))
            ->andReturn([
                [$id1, '1.2', ['-0.1250', '51.5060']],
                [$id2, '2.5', ['-0.1200', '51.5050']],
            ]);

        $token = $this->rider->createToken('test-token', ['role:rider'])->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/rides/request', [
            'pickup_latitude' => 51.5074,
            'pickup_longitude' => -0.1278,
            'pickup_address' => 'London Eye',
            'destination_latitude' => 51.5204,
            'destination_longitude' => -0.1482,
            'destination_address' => 'Regents Park',
            'vehicle_type_id' => $this->standardVehicleType->id,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Ride requested successfully.',
            ])
            ->assertJsonStructure([
                'ride' => [
                    'id',
                    'rider_id',
                    'pickup_address',
                    'status',
                    'otp',
                    'estimated_fare',
                ]
            ]);

        $rideId = $response->json('ride.id');
        $otp = $response->json('ride.otp');
        $this->assertEquals(6, strlen($otp)); // Ensure 6-digit OTP

        // Verify RideRequests were generated for the nearest drivers
        $requests = RideRequest::where('ride_id', $rideId)->get();
        $this->assertCount(2, $requests);
        $this->assertEquals((int) $id1, $requests[0]->driver_profile_id);
        $this->assertEquals((int) $id2, $requests[1]->driver_profile_id);
        $this->assertEquals(RideRequestStatus::PENDING, $requests[0]->status);
        
        // Check expires_at is set to roughly 30 seconds from now
        $this->assertNotNull($requests[0]->expires_at);
        $this->assertTrue($requests[0]->expires_at->isAfter(now()->addSeconds(25)));
    }

    /**
     * Test automatic transition of expired ride requests.
     */
    public function test_driver_ride_requests_expiration()
    {
        $driver = $this->createDriver('Driver Bob', '+447911444444', $this->standardVehicleType);
        $driverProfile = $driver['profile'];

        // Create a Ride
        $ride = Ride::create([
            'rider_id' => $this->rider->id,
            'vehicle_type_id' => $this->standardVehicleType->id,
            'pickup_address' => 'Pickup Addr',
            'pickup_latitude' => 51.5074,
            'pickup_longitude' => -0.1278,
            'destination_address' => 'Dest Addr',
            'destination_latitude' => 51.5204,
            'destination_longitude' => -0.1482,
            'status' => RideStatus::PENDING,
            'otp' => '123456',
            'estimated_distance' => 2.0,
            'estimated_duration' => 5,
            'estimated_fare' => 10.00,
        ]);

        // Create an expired request
        $requestExpired = RideRequest::create([
            'ride_id' => $ride->id,
            'driver_profile_id' => $driverProfile->id,
            'status' => RideRequestStatus::PENDING,
            'expires_at' => now()->subSecond(), // Already expired
        ]);

        $token = $driver['user']->createToken('driver-token', ['role:driver'])->plainTextToken;

        // Fetch driver requests
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/driver/ride-requests');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'requests' => [], // Should be empty because expired ones are automatically excluded or updated
            ]);

        // Verify request has transitioned to EXPIRED in the database
        $requestExpired->refresh();
        $this->assertEquals(RideRequestStatus::EXPIRED, $requestExpired->status);
    }

    /**
     * Test accepting a ride request successfully.
     */
    public function test_driver_can_accept_ride_request_successfully()
    {
        $driver1 = $this->createDriver('Driver 1', '+447911555555', $this->standardVehicleType);
        $driver2 = $this->createDriver('Driver 2', '+447911666666', $this->standardVehicleType);

        $ride = Ride::create([
            'rider_id' => $this->rider->id,
            'vehicle_type_id' => $this->standardVehicleType->id,
            'pickup_address' => 'Pickup Addr',
            'pickup_latitude' => 51.5074,
            'pickup_longitude' => -0.1278,
            'destination_address' => 'Dest Addr',
            'destination_latitude' => 51.5204,
            'destination_longitude' => -0.1482,
            'status' => RideStatus::PENDING,
            'otp' => '123456',
            'estimated_distance' => 2.0,
            'estimated_duration' => 5,
            'estimated_fare' => 10.00,
        ]);

        $req1 = RideRequest::create([
            'ride_id' => $ride->id,
            'driver_profile_id' => $driver1['profile']->id,
            'status' => RideRequestStatus::PENDING,
            'expires_at' => now()->addMinutes(5),
        ]);

        $req2 = RideRequest::create([
            'ride_id' => $ride->id,
            'driver_profile_id' => $driver2['profile']->id,
            'status' => RideRequestStatus::PENDING,
            'expires_at' => now()->addMinutes(5),
        ]);

        $token = $driver1['user']->createToken('driver-token', ['role:driver'])->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson("/api/v1/driver/ride-requests/{$req1->id}/accept");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Ride request accepted successfully.',
            ])
            ->assertJsonStructure([
                'ride' => ['id', 'status', 'driver_profile_id', 'accepted_at']
            ]);

        // Assert ride is accepted and driver assigned
        $ride->refresh();
        $this->assertEquals(RideStatus::ACCEPTED, $ride->status);
        $this->assertEquals($driver1['profile']->id, $ride->driver_profile_id);
        $this->assertNotNull($ride->accepted_at);

        // Assert accepted request status is ACCEPTED
        $req1->refresh();
        $this->assertEquals(RideRequestStatus::ACCEPTED, $req1->status);

        // Assert other requests for this ride are expired
        $req2->refresh();
        $this->assertEquals(RideRequestStatus::EXPIRED, $req2->status);
    }

    /**
     * Test ride acceptance race condition.
     */
    public function test_driver_accept_fails_if_already_accepted()
    {
        $driver1 = $this->createDriver('Driver 1', '+447911555555', $this->standardVehicleType);
        $driver2 = $this->createDriver('Driver 2', '+447911666666', $this->standardVehicleType);

        $ride = Ride::create([
            'rider_id' => $this->rider->id,
            'vehicle_type_id' => $this->standardVehicleType->id,
            'pickup_address' => 'Pickup Addr',
            'pickup_latitude' => 51.5074,
            'pickup_longitude' => -0.1278,
            'destination_address' => 'Dest Addr',
            'destination_latitude' => 51.5204,
            'destination_longitude' => -0.1482,
            'status' => RideStatus::ACCEPTED, // ALREADY ACCEPTED!
            'driver_profile_id' => $driver1['profile']->id,
            'otp' => '123456',
            'estimated_distance' => 2.0,
            'estimated_duration' => 5,
            'estimated_fare' => 10.00,
        ]);

        $req2 = RideRequest::create([
            'ride_id' => $ride->id,
            'driver_profile_id' => $driver2['profile']->id,
            'status' => RideRequestStatus::PENDING,
            'expires_at' => now()->addMinutes(5),
        ]);

        $token2 = $driver2['user']->createToken('driver-token', ['role:driver'])->plainTextToken;

        // Driver 2 tries to accept but ride is already accepted by Driver 1
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token2,
        ])->postJson("/api/v1/driver/ride-requests/{$req2->id}/accept");

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Ride request is no longer available.',
            ]);
    }

    /**
     * Test ride requests declining.
     */
    public function test_driver_can_decline_ride_request()
    {
        $driver = $this->createDriver('Driver 1', '+447911555555', $this->standardVehicleType);

        $ride = Ride::create([
            'rider_id' => $this->rider->id,
            'vehicle_type_id' => $this->standardVehicleType->id,
            'pickup_address' => 'Pickup Addr',
            'pickup_latitude' => 51.5074,
            'pickup_longitude' => -0.1278,
            'destination_address' => 'Dest Addr',
            'destination_latitude' => 51.5204,
            'destination_longitude' => -0.1482,
            'status' => RideStatus::PENDING,
            'otp' => '123456',
            'estimated_distance' => 2.0,
            'estimated_duration' => 5,
            'estimated_fare' => 10.00,
        ]);

        $req = RideRequest::create([
            'ride_id' => $ride->id,
            'driver_profile_id' => $driver['profile']->id,
            'status' => RideRequestStatus::PENDING,
            'expires_at' => now()->addMinutes(5),
        ]);

        $token = $driver['user']->createToken('driver-token', ['role:driver'])->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson("/api/v1/driver/ride-requests/{$req->id}/decline");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Ride request declined successfully.',
            ]);

        $req->refresh();
        $this->assertEquals(RideRequestStatus::DECLINED, $req->status);
    }

    /**
     * Test ride cancellation conditions.
     */
    public function test_rider_can_cancel_ride()
    {
        $ride = Ride::create([
            'rider_id' => $this->rider->id,
            'vehicle_type_id' => $this->standardVehicleType->id,
            'pickup_address' => 'Pickup Addr',
            'pickup_latitude' => 51.5074,
            'pickup_longitude' => -0.1278,
            'destination_address' => 'Dest Addr',
            'destination_latitude' => 51.5204,
            'destination_longitude' => -0.1482,
            'status' => RideStatus::PENDING,
            'otp' => '123456',
            'estimated_distance' => 2.0,
            'estimated_duration' => 5,
            'estimated_fare' => 10.00,
        ]);

        $token = $this->rider->createToken('test-token', ['role:rider'])->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson("/api/v1/rides/{$ride->id}/cancel", [
            'cancel_reason' => 'Changed my mind',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Ride cancelled successfully.',
            ]);

        $ride->refresh();
        $this->assertEquals(RideStatus::CANCELLED, $ride->status);
        $this->assertEquals('rider', $ride->cancelled_by);
        $this->assertEquals('Changed my mind', $ride->cancel_reason);
        $this->assertNotNull($ride->cancelled_at);
    }

    /**
     * Test ride cancellation forbidden when in progress.
     */
    public function test_rider_cannot_cancel_ride_in_progress()
    {
        $ride = Ride::create([
            'rider_id' => $this->rider->id,
            'vehicle_type_id' => $this->standardVehicleType->id,
            'pickup_address' => 'Pickup Addr',
            'pickup_latitude' => 51.5074,
            'pickup_longitude' => -0.1278,
            'destination_address' => 'Dest Addr',
            'destination_latitude' => 51.5204,
            'destination_longitude' => -0.1482,
            'status' => RideStatus::IN_PROGRESS, // IN PROGRESS!
            'otp' => '123456',
            'estimated_distance' => 2.0,
            'estimated_duration' => 5,
            'estimated_fare' => 10.00,
        ]);

        $token = $this->rider->createToken('test-token', ['role:rider'])->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson("/api/v1/rides/{$ride->id}/cancel");

        $response->assertStatus(422);
    }

    /**
     * Test ride status change logging.
     */
    public function test_ride_status_logs_are_auto_created()
    {
        // When a ride is created, RideObserver should log "pending" status
        $ride = Ride::create([
            'rider_id' => $this->rider->id,
            'vehicle_type_id' => $this->standardVehicleType->id,
            'pickup_address' => 'Pickup Addr',
            'pickup_latitude' => 51.5074,
            'pickup_longitude' => -0.1278,
            'destination_address' => 'Dest Addr',
            'destination_latitude' => 51.5204,
            'destination_longitude' => -0.1482,
            'status' => RideStatus::PENDING,
            'otp' => '123456',
            'estimated_distance' => 2.0,
            'estimated_duration' => 5,
            'estimated_fare' => 10.00,
        ]);

        $this->assertDatabaseHas('ride_status_logs', [
            'ride_id' => $ride->id,
            'old_status' => null,
            'new_status' => 'pending',
            'reason' => 'Ride created',
        ]);

        // Update status to accepted
        $ride->update(['status' => RideStatus::ACCEPTED]);

        $this->assertDatabaseHas('ride_status_logs', [
            'ride_id' => $ride->id,
            'old_status' => 'pending',
            'new_status' => 'accepted',
        ]);
    }

    /**
     * Test Rider active ride endpoint.
     */
    public function test_rider_can_get_active_ride()
    {
        $ride = Ride::create([
            'rider_id' => $this->rider->id,
            'vehicle_type_id' => $this->standardVehicleType->id,
            'pickup_address' => 'Pickup Addr',
            'pickup_latitude' => 51.5074,
            'pickup_longitude' => -0.1278,
            'destination_address' => 'Dest Addr',
            'destination_latitude' => 51.5204,
            'destination_longitude' => -0.1482,
            'status' => RideStatus::ACCEPTED,
            'otp' => '123456',
            'estimated_distance' => 2.0,
            'estimated_duration' => 5,
            'estimated_fare' => 10.00,
        ]);

        $token = $this->rider->createToken('test-token', ['role:rider'])->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/rides/active');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'ride' => [
                    'id' => $ride->id,
                    'status' => 'accepted',
                ]
            ]);
    }

    /**
     * Test Driver active ride endpoint.
     */
    public function test_driver_can_get_active_ride()
    {
        $driver = $this->createDriver('Driver Bob', '+447911444444', $this->standardVehicleType);
        $driverProfile = $driver['profile'];

        $ride = Ride::create([
            'rider_id' => $this->rider->id,
            'driver_profile_id' => $driverProfile->id,
            'vehicle_type_id' => $this->standardVehicleType->id,
            'pickup_address' => 'Pickup Addr',
            'pickup_latitude' => 51.5074,
            'pickup_longitude' => -0.1278,
            'destination_address' => 'Dest Addr',
            'destination_latitude' => 51.5204,
            'destination_longitude' => -0.1482,
            'status' => RideStatus::ACCEPTED,
            'otp' => '123456',
            'estimated_distance' => 2.0,
            'estimated_duration' => 5,
            'estimated_fare' => 10.00,
        ]);

        $token = $driver['user']->createToken('driver-token', ['role:driver'])->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/driver/active-ride');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'ride' => [
                    'id' => $ride->id,
                    'status' => 'accepted',
                    'driver_profile_id' => $driverProfile->id,
                ]
            ]);
    }
}
