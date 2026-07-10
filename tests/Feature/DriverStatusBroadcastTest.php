<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Events\DriverStatusChanged;
use App\Models\DriverProfile;
use App\Models\Ride;
use App\Models\RideRequest;
use App\Models\User;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DriverStatusBroadcastTest extends TestCase
{
    use RefreshDatabase;

    protected User $driverUser;

    protected DriverProfile $driverProfile;

    protected User $riderUser;

    protected Ride $ride;

    protected RideRequest $rideRequest;

    protected function setUp(): void
    {
        parent::setUp();

        $this->driverUser = User::create([
            'name' => 'Bob Driver',
            'phone' => '+447922222222',
            'email' => 'bob.driver@example.com',
            'password' => bcrypt('password123'),
            'role' => UserRole::DRIVER,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->driverProfile = DriverProfile::create([
            'user_id' => $this->driverUser->id,
            'license_number' => 'DL-123456',
            'license_expiry' => now()->addYears(2),
            'is_online' => false,
        ]);

        $this->riderUser = User::create([
            'name' => 'John Rider',
            'phone' => '+447911111111',
            'email' => 'john.rider@example.com',
            'password' => bcrypt('password123'),
            'role' => UserRole::RIDER,
            'status' => UserStatus::ACTIVE,
        ]);

        $vehicleType = VehicleType::create([
            'name' => 'Standard',
            'capacity' => 4,
            'base_fare' => 5.00,
            'per_km_rate' => 1.50,
            'per_minute_rate' => 0.50,
            'minimum_fare' => 7.00,
            'commission_percentage' => 20.00,
            'icon_url' => 'https://example.com/standard.png',
            'active' => true,
        ]);

        $this->ride = Ride::create([
            'rider_id' => $this->riderUser->id,
            'vehicle_type_id' => $vehicleType->id,
            'pickup_address' => 'London Eye',
            'pickup_latitude' => 51.5074,
            'pickup_longitude' => -0.1278,
            'destination_address' => 'Regents Park',
            'destination_latitude' => 51.5204,
            'destination_longitude' => -0.1482,
            'status' => 'pending',
            'otp' => '123456',
            'estimated_distance' => 2.50,
            'estimated_duration' => 5,
            'estimated_fare' => 10.00,
            'payment_method' => 'cash',
        ]);

        $this->rideRequest = RideRequest::create([
            'ride_id' => $this->ride->id,
            'driver_profile_id' => $this->driverProfile->id,
            'status' => 'pending',
        ]);
    }

    /**
     * Test toggling driver online status dispatches status changed event.
     */
    public function test_driver_status_broadcasts_on_toggle_online()
    {
        Event::fake([DriverStatusChanged::class]);
        Sanctum::actingAs($this->driverUser, ['role:driver']);

        $response = $this->postJson('/api/v1/driver/status', [
            'is_online' => true,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        Event::assertDispatched(DriverStatusChanged::class, function ($event) {
            return (int) $event->driver->id === (int) $this->driverUser->id &&
                $event->status === 'online';
        });
    }

    /**
     * Test accepting request makes driver status busy and broadcasts.
     */
    public function test_driver_status_broadcasts_busy_on_accept_ride()
    {
        Event::fake([DriverStatusChanged::class]);
        Sanctum::actingAs($this->driverUser, ['role:driver']);

        $response = $this->postJson("/api/v1/driver/ride-requests/{$this->rideRequest->id}/accept");

        $response->assertStatus(200);

        Event::assertDispatched(DriverStatusChanged::class, function ($event) {
            return (int) $event->driver->id === (int) $this->driverUser->id &&
                $event->status === 'busy';
        });
    }
}
