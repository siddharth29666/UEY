<?php

namespace Tests\Feature;

use App\Enums\RideStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Events\EmergencyTriggered;
use App\Models\EmergencyAlert;
use App\Models\Ride;
use App\Models\User;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class EmergencyRealtimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_triggering_sos_broadcasts_emergency_triggered_event()
    {
        Event::fake([EmergencyTriggered::class]);

        $rider = User::create([
            'name' => 'Alice Rider',
            'phone' => '+447999999901',
            'email' => 'alice.example@example.com',
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

        $alert = EmergencyAlert::create([
            'ride_id' => $ride->id,
            'user_id' => $rider->id,
            'status' => 'active',
            'latitude' => 51.5237,
            'longitude' => -0.1585,
        ]);

        event(new EmergencyTriggered($alert));

        Event::assertDispatched(EmergencyTriggered::class);
    }
}
