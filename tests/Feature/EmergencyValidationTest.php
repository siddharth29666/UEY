<?php

namespace Tests\Feature;

use App\Enums\RideStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\DriverProfile;
use App\Models\Ride;
use App\Models\User;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmergencyValidationTest extends TestCase
{
    use RefreshDatabase;

    protected User $rider;

    protected User $driver;

    protected DriverProfile $driverProfile;

    protected Ride $ride;

    protected VehicleType $vehicleType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rider = User::create([
            'name' => 'Alice Rider',
            'phone' => '+447999999901',
            'email' => 'alice@example.com',
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

        $this->driverProfile = DriverProfile::create([
            'user_id' => $this->driver->id,
            'license_number' => 'ABC123XYZ',
            'license_expiry' => '2030-01-01',
            'rating' => 4.8,
            'is_online' => true,
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
            'driver_profile_id' => $this->driverProfile->id,
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

    public function test_sos_invalid_coordinates_fail()
    {
        Sanctum::actingAs($this->rider, ['role:rider']);

        $response = $this->postJson("/api/v1/rides/{$this->ride->id}/sos", [
            'latitude' => 1000.0, // Invalid lat
            'longitude' => -0.1345,
            'message' => 'Help!',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['latitude']);
    }
}
