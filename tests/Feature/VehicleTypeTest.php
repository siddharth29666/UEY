<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VehicleTypeTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $rider;
    protected User $driver;

    protected function setUp(): void
    {
        parent::setUp();

        // Create Admin
        $this->admin = User::create([
            'name' => 'Alice Admin',
            'phone' => '+447999999901',
            'email' => 'admin@example.com',
            'password' => bcrypt('password123'),
            'role' => UserRole::ADMIN,
            'status' => UserStatus::ACTIVE,
        ]);

        // Create Rider
        $this->rider = User::create([
            'name' => 'Bob Rider',
            'phone' => '+447999999902',
            'email' => 'rider@example.com',
            'password' => bcrypt('password123'),
            'role' => UserRole::RIDER,
            'status' => UserStatus::ACTIVE,
        ]);
    }

    /**
     * Test public list vehicle types.
     */
    public function test_public_can_list_active_vehicle_types_only()
    {
        // Create an active vehicle type
        VehicleType::create([
            'name' => 'Sedan Active',
            'capacity' => 4,
            'base_fare' => 2.50,
            'per_km_rate' => 1.20,
            'per_minute_rate' => 0.30,
            'minimum_fare' => 5.00,
            'commission_percentage' => 15.00,
            'icon_url' => 'https://example.com/icon.png',
            'active' => true,
        ]);

        // Create an inactive vehicle type
        VehicleType::create([
            'name' => 'SUV Inactive',
            'capacity' => 6,
            'base_fare' => 3.50,
            'per_km_rate' => 1.80,
            'per_minute_rate' => 0.40,
            'minimum_fare' => 8.00,
            'commission_percentage' => 15.00,
            'icon_url' => 'https://example.com/icon.png',
            'active' => false,
        ]);

        $response = $this->getJson('/api/v1/vehicle-types');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Sedan Active')
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id', 'name', 'capacity', 'base_fare', 'per_km_rate', 'per_minute_rate', 'minimum_fare', 'commission_percentage', 'icon_url', 'active'
                    ]
                ]
            ]);

        // Ensure no timestamps are exposed
        $response->assertJsonMissingPath('data.0.created_at')
            ->assertJsonMissingPath('data.0.updated_at')
            ->assertJsonMissingPath('data.0.deleted_at');
    }

    /**
     * Test admin can list all vehicle types (active and inactive).
     */
    public function test_admin_can_list_all_vehicle_types()
    {
        Sanctum::actingAs($this->admin, ['role:admin']);

        VehicleType::create([
            'name' => 'Type A',
            'capacity' => 4,
            'base_fare' => 2.50,
            'per_km_rate' => 1.20,
            'per_minute_rate' => 0.30,
            'minimum_fare' => 5.00,
            'commission_percentage' => 15.00,
            'icon_url' => 'https://example.com/icon.png',
            'active' => true,
        ]);

        VehicleType::create([
            'name' => 'Type B',
            'capacity' => 6,
            'base_fare' => 3.50,
            'per_km_rate' => 1.80,
            'per_minute_rate' => 0.40,
            'minimum_fare' => 8.00,
            'commission_percentage' => 15.00,
            'icon_url' => 'https://example.com/icon.png',
            'active' => false,
        ]);

        $response = $this->getJson('/api/v1/admin/vehicle-types');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');
    }

    /**
     * Test admin can create a vehicle type.
     */
    public function test_admin_can_create_vehicle_type()
    {
        Sanctum::actingAs($this->admin, ['role:admin']);

        $payload = [
            'name' => 'Sedan New',
            'capacity' => 4,
            'base_fare' => 2.50,
            'per_km_rate' => 1.20,
            'per_minute_rate' => 0.30,
            'minimum_fare' => 5.00,
            'commission_percentage' => 15.00,
            'icon_url' => 'https://example.com/icon.png',
            'active' => true,
        ];

        $response = $this->postJson('/api/v1/admin/vehicle-types', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Vehicle type created successfully.');

        $this->assertDatabaseHas('vehicle_types', [
            'name' => 'Sedan New',
            'capacity' => 4,
            'active' => true,
        ]);
    }

    /**
     * Test admin validation for creating vehicle type.
     */
    public function test_admin_create_validation()
    {
        Sanctum::actingAs($this->admin, ['role:admin']);

        // Missing required fields
        $response = $this->postJson('/api/v1/admin/vehicle-types', []);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'capacity', 'base_fare', 'per_km_rate', 'per_minute_rate', 'minimum_fare', 'commission_percentage']);

        // Invalid numeric inputs
        $response = $this->postJson('/api/v1/admin/vehicle-types', [
            'name' => 'Invalid Type',
            'capacity' => 0,
            'base_fare' => -1,
            'per_km_rate' => -0.5,
            'per_minute_rate' => 'not-numeric',
            'minimum_fare' => -5,
            'commission_percentage' => 105,
            'icon_url' => 'invalid-url',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['capacity', 'base_fare', 'per_km_rate', 'per_minute_rate', 'minimum_fare', 'commission_percentage', 'icon_url']);
    }

    /**
     * Test admin can show details.
     */
    public function test_admin_can_show_vehicle_type()
    {
        Sanctum::actingAs($this->admin, ['role:admin']);

        $type = VehicleType::create([
            'name' => 'Type Details',
            'capacity' => 4,
            'base_fare' => 2.50,
            'per_km_rate' => 1.20,
            'per_minute_rate' => 0.30,
            'minimum_fare' => 5.00,
            'commission_percentage' => 15.00,
            'icon_url' => 'https://example.com/icon.png',
            'active' => true,
        ]);

        $response = $this->getJson("/api/v1/admin/vehicle-types/{$type->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Type Details');
    }

    /**
     * Test admin can update details.
     */
    public function test_admin_can_update_vehicle_type()
    {
        Sanctum::actingAs($this->admin, ['role:admin']);

        $type = VehicleType::create([
            'name' => 'Original Name',
            'capacity' => 4,
            'base_fare' => 2.50,
            'per_km_rate' => 1.20,
            'per_minute_rate' => 0.30,
            'minimum_fare' => 5.00,
            'commission_percentage' => 15.00,
            'icon_url' => 'https://example.com/icon.png',
            'active' => true,
        ]);

        $payload = [
            'name' => 'Updated Name',
            'capacity' => 5,
            'base_fare' => 3.00,
            'per_km_rate' => 1.40,
            'per_minute_rate' => 0.35,
            'minimum_fare' => 6.00,
            'commission_percentage' => 15.00,
            'icon_url' => 'https://example.com/new-icon.png',
            'active' => false,
        ];

        $response = $this->putJson("/api/v1/admin/vehicle-types/{$type->id}", $payload);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Vehicle type updated successfully.');

        $this->assertDatabaseHas('vehicle_types', [
            'id' => $type->id,
            'name' => 'Updated Name',
            'capacity' => 5,
            'active' => false,
        ]);
    }

    /**
     * Test admin can soft delete.
     */
    public function test_admin_can_delete_vehicle_type()
    {
        Sanctum::actingAs($this->admin, ['role:admin']);

        $type = VehicleType::create([
            'name' => 'Type To Delete',
            'capacity' => 4,
            'base_fare' => 2.50,
            'per_km_rate' => 1.20,
            'per_minute_rate' => 0.30,
            'minimum_fare' => 5.00,
            'commission_percentage' => 15.00,
            'icon_url' => 'https://example.com/icon.png',
            'active' => true,
        ]);

        $response = $this->deleteJson("/api/v1/admin/vehicle-types/{$type->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Vehicle type deleted successfully.');

        $this->assertSoftDeleted('vehicle_types', [
            'id' => $type->id,
        ]);
    }

    /**
     * Test non-admin cannot access admin endpoints.
     */
    public function test_non_admin_cannot_access_admin_endpoints()
    {
        Sanctum::actingAs($this->rider);

        $response = $this->getJson('/api/v1/admin/vehicle-types');
        $response->assertStatus(403);

        $response = $this->postJson('/api/v1/admin/vehicle-types', []);
        $response->assertStatus(403);
    }

    /**
     * Test driver registration validation.
     */
    public function test_driver_registration_fails_if_vehicle_type_inactive_or_missing()
    {
        // 1. Missing vehicle type
        $response = $this->postJson('/api/v1/register/driver', [
            'name' => 'John Driver',
            'email' => 'john.driver@example.com',
            'phone' => '+447911999888',
            'password' => 'password123',
            'license_number' => 'DL-999888',
            'license_expiry' => '2027-06-21',
            'vehicle_make' => 'Toyota',
            'vehicle_model' => 'Prius',
            'vehicle_year' => 2020,
            'vehicle_color' => 'White',
            'vehicle_plate' => 'WP-1234',
            'vehicle_type_id' => 9999, // Non-existent
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Selected vehicle type is unavailable.',
            ]);

        // 2. Inactive vehicle type
        $inactiveType = VehicleType::create([
            'name' => 'Inactive Type',
            'capacity' => 4,
            'base_fare' => 2.50,
            'per_km_rate' => 1.20,
            'per_minute_rate' => 0.30,
            'minimum_fare' => 5.00,
            'commission_percentage' => 15.00,
            'icon_url' => 'https://example.com/icon.png',
            'active' => false,
        ]);

        $response = $this->postJson('/api/v1/register/driver', [
            'name' => 'John Driver',
            'email' => 'john.driver@example.com',
            'phone' => '+447911999888',
            'password' => 'password123',
            'license_number' => 'DL-999888',
            'license_expiry' => '2027-06-21',
            'vehicle_make' => 'Toyota',
            'vehicle_model' => 'Prius',
            'vehicle_year' => 2020,
            'vehicle_color' => 'White',
            'vehicle_plate' => 'WP-1234',
            'vehicle_type_id' => $inactiveType->id,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Selected vehicle type is unavailable.',
            ]);
    }

    /**
     * Test ride request validation.
     */
    public function test_ride_request_fails_if_vehicle_type_inactive_or_missing()
    {
        Sanctum::actingAs($this->rider);

        // 1. Missing vehicle type
        $response = $this->postJson('/api/v1/rides', [
            'pickup_latitude' => 51.5074,
            'pickup_longitude' => -0.1278,
            'pickup_address' => 'London Eye, London',
            'destination_latitude' => 51.5204,
            'destination_longitude' => -0.1482,
            'destination_address' => "Regent's Park, London",
            'vehicle_type_id' => 9999, // Non-existent
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Selected vehicle type is unavailable.',
            ]);

        // 2. Inactive vehicle type
        $inactiveType = VehicleType::create([
            'name' => 'Inactive Type B',
            'capacity' => 4,
            'base_fare' => 2.50,
            'per_km_rate' => 1.20,
            'per_minute_rate' => 0.30,
            'minimum_fare' => 5.00,
            'commission_percentage' => 15.00,
            'icon_url' => 'https://example.com/icon.png',
            'active' => false,
        ]);

        $response = $this->postJson('/api/v1/rides', [
            'pickup_latitude' => 51.5074,
            'pickup_longitude' => -0.1278,
            'pickup_address' => 'London Eye, London',
            'destination_latitude' => 51.5204,
            'destination_longitude' => -0.1482,
            'destination_address' => "Regent's Park, London",
            'vehicle_type_id' => $inactiveType->id,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Selected vehicle type is unavailable.',
            ]);
    }

    /**
     * Test admin can activate and deactivate a vehicle type status.
     */
    public function test_admin_can_patch_vehicle_type_status()
    {
        Sanctum::actingAs($this->admin, ['role:admin']);

        $type = VehicleType::create([
            'name' => 'Status Toggle Type',
            'capacity' => 4,
            'base_fare' => 2.50,
            'per_km_rate' => 1.20,
            'per_minute_rate' => 0.30,
            'minimum_fare' => 5.00,
            'commission_percentage' => 15.00,
            'icon_url' => 'https://example.com/icon.png',
            'active' => true,
        ]);

        // Deactivate
        $response = $this->patchJson("/api/v1/admin/vehicle-types/{$type->id}/status", [
            'active' => false,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Vehicle type status updated successfully.')
            ->assertJsonPath('data.active', false);

        $this->assertFalse($type->refresh()->active);

        // Activate
        $response = $this->patchJson("/api/v1/admin/vehicle-types/{$type->id}/status", [
            'active' => true,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.active', true);

        $this->assertTrue($type->refresh()->active);

        // Validation error
        $response = $this->patchJson("/api/v1/admin/vehicle-types/{$type->id}/status", []);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['active']);
    }

    /**
     * Test admin can restore a soft deleted vehicle type.
     */
    public function test_admin_can_restore_soft_deleted_vehicle_type()
    {
        Sanctum::actingAs($this->admin, ['role:admin']);

        $type = VehicleType::create([
            'name' => 'Restore Me',
            'capacity' => 4,
            'base_fare' => 2.50,
            'per_km_rate' => 1.20,
            'per_minute_rate' => 0.30,
            'minimum_fare' => 5.00,
            'commission_percentage' => 15.00,
            'icon_url' => 'https://example.com/icon.png',
            'active' => true,
        ]);

        $type->delete();
        $this->assertSoftDeleted('vehicle_types', ['id' => $type->id]);

        $response = $this->postJson("/api/v1/admin/vehicle-types/{$type->id}/restore");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Vehicle type restored successfully.');

        $this->assertDatabaseHas('vehicle_types', [
            'id' => $type->id,
            'deleted_at' => null,
        ]);
    }

    /**
     * Test public API excludes soft deleted vehicle types.
     */
    public function test_public_api_excludes_soft_deleted_vehicle_types()
    {
        $type = VehicleType::create([
            'name' => 'Deleted Public Type',
            'capacity' => 4,
            'base_fare' => 2.50,
            'per_km_rate' => 1.20,
            'per_minute_rate' => 0.30,
            'minimum_fare' => 5.00,
            'commission_percentage' => 15.00,
            'icon_url' => 'https://example.com/icon.png',
            'active' => true,
        ]);

        $type->delete();

        $response = $this->getJson('/api/v1/vehicle-types');
        $response->assertStatus(200)
            ->assertJsonMissing([['name' => 'Deleted Public Type']]);
    }

    /**
     * Test driver registration rejects deleted vehicle types.
     */
    public function test_driver_registration_rejects_deleted_vehicle_types()
    {
        $type = VehicleType::create([
            'name' => 'Deleted Driver Type',
            'capacity' => 4,
            'base_fare' => 2.50,
            'per_km_rate' => 1.20,
            'per_minute_rate' => 0.30,
            'minimum_fare' => 5.00,
            'commission_percentage' => 15.00,
            'icon_url' => 'https://example.com/icon.png',
            'active' => true,
        ]);

        $type->delete();

        $response = $this->postJson('/api/v1/register/driver', [
            'name' => 'John Driver',
            'email' => 'john.driver@example.com',
            'phone' => '+447911999888',
            'password' => 'password123',
            'license_number' => 'DL-999888',
            'license_expiry' => '2027-06-21',
            'vehicle_make' => 'Toyota',
            'vehicle_model' => 'Prius',
            'vehicle_year' => 2020,
            'vehicle_color' => 'White',
            'vehicle_plate' => 'WP-1234',
            'vehicle_type_id' => $type->id,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Selected vehicle type is unavailable.',
            ]);
    }

    /**
     * Test ride request rejects deleted vehicle types.
     */
    public function test_ride_request_rejects_deleted_vehicle_types()
    {
        Sanctum::actingAs($this->rider);

        $type = VehicleType::create([
            'name' => 'Deleted Ride Type',
            'capacity' => 4,
            'base_fare' => 2.50,
            'per_km_rate' => 1.20,
            'per_minute_rate' => 0.30,
            'minimum_fare' => 5.00,
            'commission_percentage' => 15.00,
            'icon_url' => 'https://example.com/icon.png',
            'active' => true,
        ]);

        $type->delete();

        $response = $this->postJson('/api/v1/rides', [
            'pickup_latitude' => 51.5074,
            'pickup_longitude' => -0.1278,
            'pickup_address' => 'London Eye, London',
            'destination_latitude' => 51.5204,
            'destination_longitude' => -0.1482,
            'destination_address' => "Regent's Park, London",
            'vehicle_type_id' => $type->id,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Selected vehicle type is unavailable.',
            ]);
    }
}
