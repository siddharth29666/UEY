<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\DriverProfile;
use App\Models\Ride;
use App\Models\User;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PresenceChannelTest extends TestCase
{
    use RefreshDatabase;

    protected User $driverUser;

    protected DriverProfile $driverProfile;

    protected User $riderUser;

    protected User $adminUser;

    protected Ride $ride;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'broadcasting.default' => 'pusher',
            'broadcasting.connections.pusher.key' => 'dummy-key',
            'broadcasting.connections.pusher.secret' => 'dummy-secret',
            'broadcasting.connections.pusher.app_id' => 'dummy-app-id',
        ]);

        // Reload channels.php so the routes register on the resolved Pusher broadcaster
        require base_path('routes/channels.php');

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

        $this->adminUser = User::create([
            'name' => 'Alice Admin',
            'phone' => '+447999999999',
            'email' => 'alice.admin@example.com',
            'password' => bcrypt('password123'),
            'role' => UserRole::ADMIN,
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
     * Test rider channel auth success and fail.
     */
    public function test_rider_channel_authorization()
    {
        Sanctum::actingAs($this->riderUser, ['role:rider']);

        // Authorize own channel
        $response = $this->postJson('/api/broadcasting/auth', [
            'channel_name' => 'private-rider.'.$this->riderUser->id,
            'socket_id' => '1234.5678',
        ]);

        $response->assertStatus(200);

        // Fail authorizing other rider's channel
        $response = $this->postJson('/api/broadcasting/auth', [
            'channel_name' => 'private-rider.'.$this->driverUser->id,
            'socket_id' => '1234.5678',
        ]);

        $response->assertStatus(403);
    }

    /**
     * Test presence channel authorization.
     */
    public function test_presence_channels_authorization()
    {
        // 1. Driver on drivers presence channel
        Sanctum::actingAs($this->driverUser, ['role:driver']);
        $response = $this->postJson('/api/broadcasting/auth', [
            'channel_name' => 'presence-drivers',
            'socket_id' => '1234.5678',
        ]);
        $response->assertStatus(200);

        // Rider on drivers presence channel (fail)
        Sanctum::actingAs($this->riderUser, ['role:rider']);
        $response = $this->postJson('/api/broadcasting/auth', [
            'channel_name' => 'presence-drivers',
            'socket_id' => '1234.5678',
        ]);
        $response->assertStatus(403);

        // 2. Admin on admins presence channel
        Sanctum::actingAs($this->adminUser, ['role:admin']);
        $response = $this->postJson('/api/broadcasting/auth', [
            'channel_name' => 'presence-admins',
            'socket_id' => '1234.5678',
        ]);
        $response->assertStatus(200);
    }
}
