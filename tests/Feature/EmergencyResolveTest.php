<?php

namespace Tests\Feature;

use App\Enums\RideStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\EmergencyAlert;
use App\Models\Ride;
use App\Models\User;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmergencyResolveTest extends TestCase
{
    use RefreshDatabase;

    protected User $rider;

    protected User $admin;

    protected Ride $ride;

    protected EmergencyAlert $alert;

    protected VehicleType $vehicleType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rider = User::create([
            'name' => 'Alice Rider',
            'phone' => '+447999999901',
            'email' => 'alice.rider@example.com',
            'password' => bcrypt('password123'),
            'role' => UserRole::RIDER,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->admin = User::create([
            'name' => 'Alice Admin',
            'phone' => '+447999999999',
            'email' => 'alice.admin@example.com',
            'password' => bcrypt('password123'),
            'role' => UserRole::ADMIN,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->vehicleType = VehicleType::create([
            'name' => 'Economy',
            'base_fare' => 5.00,
            'per_km_rate' => 1.50,
            'per_minute_rate' => 0.20,
            'minimum_fare' => 5.00,
            'icon_url' => 'http://example.com/icon.png',
        ]);

        $this->ride = Ride::create([
            'rider_id' => $this->rider->id,
            'vehicle_type_id' => $this->vehicleType->id,
            'pickup_address' => '221 Baker St',
            'pickup_latitude' => 51.5237,
            'pickup_longitude' => -0.1585,
            'destination_address' => 'London Eye',
            'destination_latitude' => 51.5033,
            'destination_longitude' => -0.1195,
            'status' => RideStatus::ACCEPTED,
            'otp' => '1234',
            'estimated_distance' => 10.0,
            'estimated_duration' => 20,
            'estimated_fare' => 15.00,
        ]);

        $this->alert = EmergencyAlert::create([
            'ride_id' => $this->ride->id,
            'user_id' => $this->rider->id,
            'driver_id' => null,
            'status' => 'active',
            'latitude' => 51.5237,
            'longitude' => -0.1585,
        ]);
    }

    public function test_rider_can_resolve_their_own_sos()
    {
        Sanctum::actingAs($this->rider, ['role:rider']);

        $response = $this->postJson("/api/v1/emergency-alerts/{$this->alert->id}/resolve", [
            'admin_note' => 'Accidentally triggered, all good.',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('emergency_alerts', [
            'id' => $this->alert->id,
            'status' => 'resolved',
            'resolved_by' => $this->rider->id,
        ]);
    }

    public function test_admin_can_assign_and_resolve_and_close_sos()
    {
        Sanctum::actingAs($this->admin, ['role:admin']);

        // 1. Assign Admin
        $response = $this->postJson("/api/v1/admin/emergency-alerts/{$this->alert->id}/assign");
        $response->assertStatus(200);

        $this->assertDatabaseHas('emergency_alerts', [
            'id' => $this->alert->id,
            'status' => 'assigned',
            'resolved_by' => $this->admin->id,
        ]);

        // 2. Resolve Admin
        $response = $this->postJson("/api/v1/admin/emergency-alerts/{$this->alert->id}/resolve", [
            'admin_note' => 'Dispatched police. Resolved safely.',
        ]);
        $response->assertStatus(200);

        $this->assertDatabaseHas('emergency_alerts', [
            'id' => $this->alert->id,
            'status' => 'resolved',
        ]);

        // 3. Close Admin
        $response = $this->postJson("/api/v1/admin/emergency-alerts/{$this->alert->id}/close");
        $response->assertStatus(200);

        $this->assertDatabaseHas('emergency_alerts', [
            'id' => $this->alert->id,
            'status' => 'closed',
        ]);
    }
}
