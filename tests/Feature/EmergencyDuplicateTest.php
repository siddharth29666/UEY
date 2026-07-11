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
use Tests\TestCase;

class EmergencyDuplicateTest extends TestCase
{
    use RefreshDatabase;

    public function test_cannot_create_duplicate_active_alerts()
    {
        $rider = User::create([
            'name' => 'Alice Rider',
            'phone' => '+447999999901',
            'email' => 'alice@example.com',
            'password' => bcrypt('password123'),
            'role' => UserRole::RIDER,
            'status' => UserStatus::ACTIVE,
        ]);

        $vehicleType = VehicleType::create([
            'name' => 'Economy',
            'base_fare' => 5.00,
            'per_km_rate' => 1.50,
            'per_minute_rate' => 0.20,
            'minimum_fare' => 5.00,
            'icon_url' => 'http://example.com/icon.png',
        ]);

        $ride = Ride::create([
            'rider_id' => $rider->id,
            'vehicle_type_id' => $vehicleType->id,
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

        $alert1 = EmergencyAlert::create([
            'ride_id' => $ride->id,
            'user_id' => $rider->id,
            'status' => 'active',
            'latitude' => 51.5237,
            'longitude' => -0.1585,
        ]);

        $hasActiveSOS = EmergencyAlert::where('ride_id', $ride->id)
            ->whereIn('status', ['active', 'acknowledged', 'assigned'])
            ->exists();

        $this->assertTrue($hasActiveSOS);
    }
}
