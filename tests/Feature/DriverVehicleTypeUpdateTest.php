<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\VehicleStatus;
use App\Models\DriverProfile;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DriverVehicleTypeUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected User $driverUser;
    protected DriverProfile $driverProfile;
    protected Vehicle $vehicle;
    protected VehicleType $typeSedan;
    protected VehicleType $typeSUV;
    protected User $riderUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->typeSedan = VehicleType::create([
            'name' => 'Sedan',
            'base_fare' => 5.00,
            'per_km_rate' => 1.50,
            'per_minute_rate' => 0.20,
            'minimum_fare' => 7.00,
            'capacity' => 4,
            'is_active' => true,
        ]);

        $this->typeSUV = VehicleType::create([
            'name' => 'SUV',
            'base_fare' => 10.00,
            'per_km_rate' => 2.50,
            'per_minute_rate' => 0.40,
            'minimum_fare' => 15.00,
            'capacity' => 6,
            'is_active' => true,
        ]);

        $this->driverUser = User::create([
            'name' => 'Driver Dave',
            'email' => 'driver.dave@example.com',
            'phone' => '+447911000111',
            'password' => Hash::make('password123'),
            'role' => UserRole::DRIVER,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->driverProfile = DriverProfile::create([
            'user_id' => $this->driverUser->id,
            'license_number' => 'DL-TEST-1234',
            'license_expiry' => now()->addYear(),
            'is_online' => true,
        ]);

        $this->vehicle = Vehicle::create([
            'driver_profile_id' => $this->driverProfile->id,
            'vehicle_type_id' => $this->typeSedan->id,
            'make' => 'Toyota',
            'model' => 'Camry',
            'year' => 2022,
            'color' => 'Black',
            'plate_number' => 'TEST-999',
            'status' => VehicleStatus::APPROVED,
        ]);

        $this->riderUser = User::create([
            'name' => 'Rider Rachel',
            'email' => 'rider.rachel@example.com',
            'phone' => '+447911000222',
            'password' => Hash::make('password123'),
            'role' => UserRole::RIDER,
            'status' => UserStatus::ACTIVE,
        ]);
    }

    /**
     * Test 1: Driver can update vehicle_type_id and it is persisted in the database.
     */
    public function test_driver_can_update_vehicle_type_id(): void
    {
        Sanctum::actingAs($this->driverUser, ['role:driver']);

        $response = $this->putJson('/api/v1/profile', [
            'vehicle_type_id' => $this->typeSUV->id,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Profile updated successfully.',
            ]);

        // Assert persisted in vehicles table
        $this->assertDatabaseHas('vehicles', [
            'driver_profile_id' => $this->driverProfile->id,
            'vehicle_type_id' => $this->typeSUV->id,
        ]);
    }

    /**
     * Test 2: Updated vehicle_type_id and vehicle_type object are returned in profile response.
     */
    public function test_updated_vehicle_type_returned_in_profile_response(): void
    {
        Sanctum::actingAs($this->driverUser, ['role:driver']);

        $putResponse = $this->putJson('/api/v1/profile', [
            'vehicle_type_id' => $this->typeSUV->id,
        ]);

        $putResponse->assertStatus(200);

        // Check PUT response contains updated vehicle_type_id
        $this->assertEquals(
            $this->typeSUV->id,
            $putResponse->json('user.driver_profile.vehicles.0.vehicle_type_id')
        );

        // Check GET /profile response contains updated vehicle_type_id
        $getResponse = $this->getJson('/api/v1/profile');

        $getResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertEquals(
            $this->typeSUV->id,
            $getResponse->json('user.driver_profile.vehicles.0.vehicle_type_id')
        );
        $this->assertEquals(
            'SUV',
            $getResponse->json('user.driver_profile.vehicles.0.vehicle_type.name')
        );
    }

    /**
     * Test 3: Invalid/non-existent vehicle_type_id returns 422 validation error.
     */
    public function test_invalid_vehicle_type_id_returns_validation_error(): void
    {
        Sanctum::actingAs($this->driverUser, ['role:driver']);

        $response = $this->putJson('/api/v1/profile', [
            'vehicle_type_id' => 99999,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['vehicle_type_id']);
    }

    /**
     * Test 4: Rider/non-driver cannot update vehicle_type_id.
     */
    public function test_rider_cannot_update_vehicle_type_id(): void
    {
        Sanctum::actingAs($this->riderUser, ['role:rider']);

        $response = $this->putJson('/api/v1/profile', [
            'vehicle_type_id' => $this->typeSUV->id,
        ]);

        $response->assertStatus(200);

        // Assert vehicle table untouched
        $this->assertDatabaseHas('vehicles', [
            'id' => $this->vehicle->id,
            'vehicle_type_id' => $this->typeSedan->id,
        ]);
    }

    /**
     * Test 5: Existing profile update fields continue to work properly without regression.
     */
    public function test_existing_profile_fields_update_correctly(): void
    {
        Sanctum::actingAs($this->driverUser, ['role:driver']);

        $response = $this->putJson('/api/v1/profile', [
            'name' => 'Driver Dave Updated',
            'default_navigation' => 'waze',
            'auto_accept' => true,
            'vehicle_type_id' => $this->typeSUV->id,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'user' => [
                    'name' => 'Driver Dave Updated',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $this->driverUser->id,
            'name' => 'Driver Dave Updated',
        ]);

        $this->assertDatabaseHas('driver_profiles', [
            'id' => $this->driverProfile->id,
            'default_navigation' => 'waze',
            'auto_accept' => true,
        ]);

        $this->assertDatabaseHas('vehicles', [
            'driver_profile_id' => $this->driverProfile->id,
            'vehicle_type_id' => $this->typeSUV->id,
        ]);
    }
}
