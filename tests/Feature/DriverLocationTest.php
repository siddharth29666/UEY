<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Events\DriverLocationUpdated;
use App\Models\DriverProfile;
use App\Models\Ride;
use App\Models\User;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DriverLocationTest extends TestCase
{
    use RefreshDatabase;

    protected User $driverUser;

    protected DriverProfile $driverProfile;

    protected User $riderUser;

    protected Ride $ride;

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
            'is_online' => true,
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
            'driver_profile_id' => $this->driverProfile->id,
            'vehicle_type_id' => $vehicleType->id,
            'pickup_address' => 'London Eye',
            'pickup_latitude' => 51.5074,
            'pickup_longitude' => -0.1278,
            'destination_address' => 'Regents Park',
            'destination_latitude' => 51.5204,
            'destination_longitude' => -0.1482,
            'status' => 'accepted',
            'otp' => '123456',
            'estimated_distance' => 2.50,
            'estimated_duration' => 5,
            'estimated_fare' => 10.00,
            'payment_method' => 'cash',
        ]);
    }

    /**
     * Test coordinates update saves to history table and dispatches broadcast event when driver has active ride.
     */
    public function test_driver_location_update_saves_history_and_broadcasts_on_active_ride()
    {
        Event::fake([DriverLocationUpdated::class]);
        Sanctum::actingAs($this->driverUser, ['role:driver']);

        $response = $this->postJson('/api/v1/driver/location', [
            'latitude' => 51.5080,
            'longitude' => -0.1280,
            'heading' => 90.0,
            'speed' => 35.5,
            'accuracy' => 5.0,
            'timestamp' => 1700000000,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        // Verify driver profile coordinates updated
        $this->assertEquals(51.5080, (float) $this->driverProfile->refresh()->current_latitude);

        // Verify coordinates history logged
        $this->assertDatabaseHas('driver_locations', [
            'driver_id' => $this->driverUser->id,
            'ride_id' => $this->ride->id,
            'latitude' => 51.5080,
            'longitude' => -0.1280,
            'speed' => 35.5,
        ]);

        // Verify broadcast event dispatched
        Event::assertDispatched(DriverLocationUpdated::class, function ($event) {
            return (int) $event->ride->id === (int) $this->ride->id &&
                $event->latitude === 51.5080 &&
                $event->speed === 35.5;
        });
    }
}
