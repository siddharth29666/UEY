<?php

namespace Tests\Feature;

use App\Enums\RideStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\DriverProfile;
use App\Models\Ride;
use App\Models\User;
use App\Models\VehicleType;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DriverRideHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected User $driverA;
    protected DriverProfile $driverProfileA;
    protected User $driverB;
    protected DriverProfile $driverProfileB;
    protected User $rider;
    protected VehicleType $vehicleType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vehicleType = VehicleType::create([
            'name' => 'Standard Sedan',
            'base_fare' => 5.00,
            'per_km_rate' => 1.50,
            'per_minute_rate' => 0.20,
            'minimum_fare' => 7.00,
            'capacity' => 4,
            'is_active' => true,
        ]);

        $this->rider = User::create([
            'name' => 'Alice Rider',
            'email' => 'alice@example.com',
            'phone' => '+447911000001',
            'password' => bcrypt('password'),
            'role' => UserRole::RIDER,
            'status' => UserStatus::ACTIVE,
        ]);

        // Driver A
        $this->driverA = User::create([
            'name' => 'Driver Alpha',
            'email' => 'driver.a@example.com',
            'phone' => '+447911000002',
            'password' => bcrypt('password'),
            'role' => UserRole::DRIVER,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->driverProfileA = DriverProfile::create([
            'user_id' => $this->driverA->id,
            'vehicle_type_id' => $this->vehicleType->id,
            'license_number' => 'DL-ALPHA-123',
            'is_online' => true,
            'is_available' => true,
        ]);

        // Driver B
        $this->driverB = User::create([
            'name' => 'Driver Beta',
            'email' => 'driver.b@example.com',
            'phone' => '+447911000003',
            'password' => bcrypt('password'),
            'role' => UserRole::DRIVER,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->driverProfileB = DriverProfile::create([
            'user_id' => $this->driverB->id,
            'vehicle_type_id' => $this->vehicleType->id,
            'license_number' => 'DL-BETA-456',
            'is_online' => true,
            'is_available' => true,
        ]);
    }

    /**
     * Test 1: Authenticated driver can retrieve ride history successfully.
     */
    public function test_driver_can_get_ride_history_successfully(): void
    {
        Sanctum::actingAs($this->driverA, ['role:driver']);

        Ride::create([
            'rider_id' => $this->rider->id,
            'driver_profile_id' => $this->driverProfileA->id,
            'vehicle_type_id' => $this->vehicleType->id,
            'pickup_address' => '10 Downing St',
            'pickup_latitude' => 51.5034,
            'pickup_longitude' => -0.1276,
            'destination_address' => 'Tower Bridge',
            'destination_latitude' => 51.5055,
            'destination_longitude' => -0.0754,
            'status' => RideStatus::COMPLETED,
            'estimated_distance' => 4.2,
            'estimated_duration' => 15,
            'estimated_fare' => 12.50,
            'actual_fare' => 12.50,
            'completed_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/driver/rides/history');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'rides' => [
                    '*' => ['id', 'rider_id', 'driver_profile_id', 'status', 'pickup_address', 'destination_address'],
                ],
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
                'links' => ['first', 'last', 'prev', 'next'],
            ]);

        $this->assertCount(1, $response->json('rides'));
    }

    /**
     * Test 2: Endpoint does NOT trigger route model binding "history" not found error.
     */
    public function test_driver_ride_history_route_does_not_trigger_model_binding_not_found(): void
    {
        Sanctum::actingAs($this->driverA, ['role:driver']);

        $response = $this->getJson('/api/v1/driver/rides/history');

        $response->assertStatus(200);
        $this->assertNotEquals(404, $response->status());
        $this->assertStringNotContainsString('No query results for model', $response->content());
    }

    /**
     * Test 3: Security Scoping - Only authenticated driver's rides are returned.
     */
    public function test_driver_only_sees_their_own_rides(): void
    {
        // Ride for Driver A
        Ride::create([
            'rider_id' => $this->rider->id,
            'driver_profile_id' => $this->driverProfileA->id,
            'vehicle_type_id' => $this->vehicleType->id,
            'pickup_address' => 'Pickup A',
            'destination_address' => 'Drop A',
            'pickup_latitude' => 51.5,
            'pickup_longitude' => -0.1,
            'destination_latitude' => 51.6,
            'destination_longitude' => -0.2,
            'status' => RideStatus::COMPLETED,
            'estimated_distance' => 5.0,
            'estimated_duration' => 20,
            'estimated_fare' => 15.00,
        ]);

        // Ride for Driver B
        Ride::create([
            'rider_id' => $this->rider->id,
            'driver_profile_id' => $this->driverProfileB->id,
            'vehicle_type_id' => $this->vehicleType->id,
            'pickup_address' => 'Pickup B',
            'destination_address' => 'Drop B',
            'pickup_latitude' => 51.5,
            'pickup_longitude' => -0.1,
            'destination_latitude' => 51.6,
            'destination_longitude' => -0.2,
            'status' => RideStatus::COMPLETED,
            'estimated_distance' => 5.0,
            'estimated_duration' => 20,
            'estimated_fare' => 15.00,
        ]);

        // Act as Driver A
        Sanctum::actingAs($this->driverA, ['role:driver']);

        $response = $this->getJson('/api/v1/driver/rides/history');

        $response->assertStatus(200);
        $rides = $response->json('rides');
        $this->assertCount(1, $rides);
        $this->assertEquals($this->driverProfileA->id, $rides[0]['driver_profile_id']);
    }

    /**
     * Test 4: Pagination works correctly with per_page parameter.
     */
    public function test_driver_ride_history_pagination(): void
    {
        Sanctum::actingAs($this->driverA, ['role:driver']);

        for ($i = 1; $i <= 5; $i++) {
            Ride::create([
                'rider_id' => $this->rider->id,
                'driver_profile_id' => $this->driverProfileA->id,
                'vehicle_type_id' => $this->vehicleType->id,
                'pickup_address' => "Pickup {$i}",
                'destination_address' => "Drop {$i}",
                'pickup_latitude' => 51.5,
                'pickup_longitude' => -0.1,
                'destination_latitude' => 51.6,
                'destination_longitude' => -0.2,
                'status' => RideStatus::COMPLETED,
                'estimated_distance' => 5.0,
                'estimated_duration' => 20,
                'estimated_fare' => 15.00,
            ]);
        }

        $response = $this->getJson('/api/v1/driver/rides/history?per_page=2&page=1');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('rides'));
        $this->assertEquals(5, $response->json('meta.total'));
        $this->assertEquals(3, $response->json('meta.last_page'));
    }

    /**
     * Test 5: Status filtering (e.g. status=completed vs status=cancelled).
     */
    public function test_driver_ride_history_status_filtering(): void
    {
        Sanctum::actingAs($this->driverA, ['role:driver']);

        Ride::create([
            'rider_id' => $this->rider->id,
            'driver_profile_id' => $this->driverProfileA->id,
            'vehicle_type_id' => $this->vehicleType->id,
            'pickup_address' => 'Pickup 1',
            'destination_address' => 'Drop 1',
            'pickup_latitude' => 51.5,
            'pickup_longitude' => -0.1,
            'destination_latitude' => 51.6,
            'destination_longitude' => -0.2,
            'status' => RideStatus::COMPLETED,
            'estimated_distance' => 5.0,
            'estimated_duration' => 20,
            'estimated_fare' => 15.00,
        ]);

        Ride::create([
            'rider_id' => $this->rider->id,
            'driver_profile_id' => $this->driverProfileA->id,
            'vehicle_type_id' => $this->vehicleType->id,
            'pickup_address' => 'Pickup 2',
            'destination_address' => 'Drop 2',
            'pickup_latitude' => 51.5,
            'pickup_longitude' => -0.1,
            'destination_latitude' => 51.6,
            'destination_longitude' => -0.2,
            'status' => RideStatus::CANCELLED,
            'estimated_distance' => 5.0,
            'estimated_duration' => 20,
            'estimated_fare' => 15.00,
        ]);

        $completedResponse = $this->getJson('/api/v1/driver/rides/history?status=completed');
        $completedResponse->assertStatus(200);
        $this->assertCount(1, $completedResponse->json('rides'));
        $this->assertEquals('completed', $completedResponse->json('rides.0.status'));

        $cancelledResponse = $this->getJson('/api/v1/driver/rides/history?status=cancelled');
        $cancelledResponse->assertStatus(200);
        $this->assertCount(1, $cancelledResponse->json('rides'));
        $this->assertEquals('cancelled', $cancelledResponse->json('rides.0.status'));
    }

    /**
     * Test 6: Date range filtering (from and to).
     */
    public function test_driver_ride_history_date_filtering(): void
    {
        Sanctum::actingAs($this->driverA, ['role:driver']);

        // Past ride (10 days ago)
        Ride::create([
            'rider_id' => $this->rider->id,
            'driver_profile_id' => $this->driverProfileA->id,
            'vehicle_type_id' => $this->vehicleType->id,
            'pickup_address' => 'Old Pickup',
            'destination_address' => 'Old Drop',
            'pickup_latitude' => 51.5,
            'pickup_longitude' => -0.1,
            'destination_latitude' => 51.6,
            'destination_longitude' => -0.2,
            'status' => RideStatus::COMPLETED,
            'estimated_distance' => 5.0,
            'estimated_duration' => 20,
            'estimated_fare' => 15.00,
            'created_at' => Carbon::now()->subDays(10),
        ]);

        // Today's ride
        Ride::create([
            'rider_id' => $this->rider->id,
            'driver_profile_id' => $this->driverProfileA->id,
            'vehicle_type_id' => $this->vehicleType->id,
            'pickup_address' => 'Recent Pickup',
            'destination_address' => 'Recent Drop',
            'pickup_latitude' => 51.5,
            'pickup_longitude' => -0.1,
            'destination_latitude' => 51.6,
            'destination_longitude' => -0.2,
            'status' => RideStatus::COMPLETED,
            'estimated_distance' => 5.0,
            'estimated_duration' => 20,
            'estimated_fare' => 15.00,
            'created_at' => Carbon::now(),
        ]);

        $todayStr = Carbon::now()->format('Y-m-d');
        $response = $this->getJson("/api/v1/driver/rides/history?from={$todayStr}");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('rides'));
        $this->assertEquals('Recent Pickup', $response->json('rides.0.pickup_address'));
    }

    /**
     * Test 7: Driver with no historical rides returns HTTP 200 with empty array (NOT 404).
     */
    public function test_driver_with_no_rides_returns_empty_array_200(): void
    {
        Sanctum::actingAs($this->driverA, ['role:driver']);

        $response = $this->getJson('/api/v1/driver/rides/history');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'rides' => [],
            ]);
    }

    /**
     * Test 8: Unauthenticated user cannot access driver ride history.
     */
    public function test_unauthenticated_user_cannot_access_ride_history(): void
    {
        $response = $this->getJson('/api/v1/driver/rides/history');

        $response->assertStatus(401);
    }
}
