<?php

namespace Tests\Feature;

use App\Enums\RideStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Events\EmergencyAcknowledged;
use App\Models\EmergencyAlert;
use App\Models\Ride;
use App\Models\User;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmergencyBroadcastTest extends TestCase
{
    use RefreshDatabase;

    protected User $rider;

    protected User $driver;

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

        $this->driver = User::create([
            'name' => 'Bob Driver',
            'phone' => '+447999999902',
            'email' => 'bob.driver@example.com',
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
            'driver_id' => $this->driver->id,
            'status' => 'active',
            'latitude' => 51.5237,
            'longitude' => -0.1585,
        ]);
    }

    public function test_driver_acknowledgement_broadcasts_event_on_realtime_queue()
    {
        Event::fake([EmergencyAcknowledged::class]);

        Sanctum::actingAs($this->driver, ['role:driver']);
        $response = $this->postJson("/api/v1/emergency-alerts/{$this->alert->id}/acknowledge");
        $response->assertStatus(200);

        Event::assertDispatched(EmergencyAcknowledged::class, function ($event) {
            return $event->broadcastQueue === 'realtime' && (int) $event->alert->id === (int) $this->alert->id;
        });
    }
}
