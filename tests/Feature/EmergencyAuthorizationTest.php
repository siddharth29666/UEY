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

class EmergencyAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected User $rider1;

    protected User $rider2;

    protected User $driver;

    protected Ride $ride;

    protected EmergencyAlert $alert1;

    protected VehicleType $vehicleType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rider1 = User::create([
            'name' => 'Alice Rider',
            'phone' => '+447999999901',
            'email' => 'alice1@example.com',
            'password' => bcrypt('password123'),
            'role' => UserRole::RIDER,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->rider2 = User::create([
            'name' => 'Charlie Rider',
            'phone' => '+447999999903',
            'email' => 'charlie@example.com',
            'password' => bcrypt('password123'),
            'role' => UserRole::RIDER,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->driver = User::create([
            'name' => 'Bob Driver',
            'phone' => '+447999999902',
            'email' => 'bob@example.com',
            'password' => bcrypt('password123'),
            'role' => UserRole::DRIVER,
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
            'rider_id' => $this->rider1->id,
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

        $this->alert1 = EmergencyAlert::create([
            'ride_id' => $this->ride->id,
            'user_id' => $this->rider1->id,
            'driver_id' => null,
            'status' => 'active',
            'latitude' => 51.5237,
            'longitude' => -0.1585,
        ]);
    }

    public function test_other_rider_cannot_view_or_resolve_sos()
    {
        Sanctum::actingAs($this->rider2, ['role:rider']);

        // View fails
        $response = $this->getJson("/api/v1/emergency-alerts/{$this->alert1->id}");
        $response->assertStatus(403);

        // Resolve fails
        $response = $this->postJson("/api/v1/emergency-alerts/{$this->alert1->id}/resolve");
        $response->assertStatus(403);
    }
}
