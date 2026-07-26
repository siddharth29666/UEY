<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Enums\VehicleStatus;
use App\Models\DriverProfile;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use App\Services\RideMatchingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminVehicleApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $rider;
    protected User $driverUser;
    protected DriverProfile $driverProfile;
    protected VehicleType $vehicleType;
    protected Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'status' => UserStatus::ACTIVE->value,
        ]);

        $this->rider = User::factory()->create([
            'role' => 'rider',
            'status' => UserStatus::ACTIVE->value,
        ]);

        $this->driverUser = User::factory()->create([
            'role' => 'driver',
            'status' => UserStatus::ACTIVE->value,
        ]);

        $this->driverProfile = DriverProfile::create([
            'user_id' => $this->driverUser->id,
            'license_number' => 'DL-98765432',
            'license_expiry' => '2030-12-31',
            'is_online' => true,
            'rating' => 4.90,
            'current_latitude' => 37.774929,
            'current_longitude' => -122.419416,
        ]);

        $this->vehicleType = VehicleType::create([
            'name' => 'Sedan',
            'capacity' => 4,
            'base_fare' => 5.00,
            'per_km_rate' => 1.50,
            'per_minute_rate' => 0.20,
            'minimum_fare' => 7.00,
            'icon_url' => 'https://example.com/sedan.png',
            'is_active' => true,
        ]);

        $this->vehicle = Vehicle::create([
            'driver_profile_id' => $this->driverProfile->id,
            'vehicle_type_id' => $this->vehicleType->id,
            'make' => 'Toyota',
            'model' => 'Camry',
            'year' => 2022,
            'color' => 'Black',
            'plate_number' => 'UEY-7788',
            'status' => VehicleStatus::PENDING,
        ]);
    }

    public function test_admin_can_list_vehicles_with_filters(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/vehicles?status=pending');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data');
    }

    public function test_admin_can_list_pending_vehicles(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/vehicles/pending');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.id', $this->vehicle->id)
            ->assertJsonPath('data.0.status', 'pending');
    }

    public function test_admin_can_view_vehicle_details(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/admin/vehicles/{$this->vehicle->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $this->vehicle->id)
            ->assertJsonPath('data.make', 'Toyota')
            ->assertJsonPath('data.plate_number', 'UEY-7788');
    }

    public function test_admin_can_approve_pending_vehicle(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/vehicles/{$this->vehicle->id}/approve");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseHas('vehicles', [
            'id' => $this->vehicle->id,
            'status' => 'approved',
        ]);
    }

    public function test_admin_can_reject_pending_vehicle_with_reason(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/vehicles/{$this->vehicle->id}/reject", [
                'rejection_reason' => 'Plate number mismatch with registration document.',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.rejection_reason', 'Plate number mismatch with registration document.');

        $this->assertDatabaseHas('vehicles', [
            'id' => $this->vehicle->id,
            'status' => 'rejected',
            'rejection_reason' => 'Plate number mismatch with registration document.',
        ]);
    }

    public function test_admin_can_update_vehicle_status_patch(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/v1/admin/vehicles/{$this->vehicle->id}/status", [
                'status' => 'approved',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'approved');
    }

    public function test_rider_cannot_access_admin_vehicle_endpoints(): void
    {
        $response = $this->actingAs($this->rider, 'sanctum')
            ->getJson('/api/v1/admin/vehicles');

        $response->assertStatus(403);
    }

    public function test_driver_cannot_access_admin_vehicle_endpoints(): void
    {
        $response = $this->actingAs($this->driverUser, 'sanctum')
            ->postJson("/api/v1/admin/vehicles/{$this->vehicle->id}/approve");

        $response->assertStatus(403);
    }

    public function test_unauthenticated_user_cannot_access_admin_vehicle_endpoints(): void
    {
        $response = $this->getJson('/api/v1/admin/vehicles/pending');

        $response->assertStatus(401);
    }

    public function test_non_existent_vehicle_id_returns_404(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/vehicles/999999');

        $response->assertStatus(404);
    }

    public function test_invalid_status_payload_returns_422(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/v1/admin/vehicles/{$this->vehicle->id}/status", [
                'status' => 'invalid_status_value',
            ]);

        $response->assertStatus(422);
    }

    public function test_pending_or_rejected_vehicle_cannot_be_matched_for_rides(): void
    {
        $matchingService = app(RideMatchingService::class);

        // 1. Pending vehicle should NOT be matched
        $matchedDriverPending = $matchingService->findNearestDriver(
            37.774929,
            -122.419416,
            $this->vehicleType->id
        );
        $this->assertNull($matchedDriverPending, 'Pending vehicle must not be matched for rides.');

        // 2. Approve vehicle
        $this->vehicle->update(['status' => VehicleStatus::APPROVED]);

        $matchedDriverApproved = $matchingService->findNearestDriver(
            37.774929,
            -122.419416,
            $this->vehicleType->id
        );
        $this->assertNotNull($matchedDriverApproved, 'Approved vehicle should be matched for rides.');
        $this->assertEquals($this->driverProfile->id, $matchedDriverApproved->id);

        // 3. Reject vehicle
        $this->vehicle->update(['status' => VehicleStatus::REJECTED]);

        $matchedDriverRejected = $matchingService->findNearestDriver(
            37.774929,
            -122.419416,
            $this->vehicleType->id
        );
        $this->assertNull($matchedDriverRejected, 'Rejected vehicle must not be matched for rides.');
    }
}
