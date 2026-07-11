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

class EmergencyStatisticsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $rider;

    protected Ride $ride1;

    protected Ride $ride2;

    protected VehicleType $vehicleType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Alice Admin',
            'phone' => '+447999999999',
            'email' => 'alice@example.com',
            'password' => bcrypt('password123'),
            'role' => UserRole::ADMIN,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->rider = User::create([
            'name' => 'Alice Rider',
            'phone' => '+447999999901',
            'email' => 'rider@example.com',
            'password' => bcrypt('password123'),
            'role' => UserRole::RIDER,
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

        $this->ride1 = Ride::create([
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

        $this->ride2 = Ride::create([
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
    }

    public function test_admin_can_retrieve_sos_statistics()
    {
        Sanctum::actingAs($this->admin, ['role:admin']);

        // Create resolved SOS with response time bypassing auto-overwriting timestamps
        $alert = new EmergencyAlert([
            'ride_id' => $this->ride1->id,
            'user_id' => $this->rider->id,
            'status' => 'resolved',
            'latitude' => 51.5237,
            'longitude' => -0.1585,
            'resolved_at' => now(),
            'resolved_by' => $this->admin->id,
        ]);
        $alert->created_at = now()->subMinutes(10);
        $alert->save();

        // Create active SOS
        EmergencyAlert::create([
            'ride_id' => $this->ride2->id,
            'user_id' => $this->rider->id,
            'status' => 'active',
            'latitude' => 51.5237,
            'longitude' => -0.1585,
        ]);

        $response = $this->getJson('/api/v1/admin/emergency-alerts/statistics');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.active', 1)
            ->assertJsonPath('data.resolved', 1)
            ->assertJsonPath('data.average_response_time', 600);
    }
}
