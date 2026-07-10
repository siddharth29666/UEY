<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Conversation;
use App\Models\DriverProfile;
use App\Models\Ride;
use App\Models\User;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConversationTest extends TestCase
{
    use RefreshDatabase;

    protected User $driverUser;

    protected DriverProfile $driverProfile;

    protected User $riderUser;

    protected User $intruder;

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

        $this->intruder = User::create([
            'name' => 'Intruder User',
            'phone' => '+447933333333',
            'email' => 'intruder@example.com',
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
     * Test rider/driver can create or fetch conversation thread.
     */
    public function test_user_can_create_or_retrieve_conversation_thread()
    {
        Sanctum::actingAs($this->riderUser, ['role:rider']);

        $response = $this->postJson('/api/v1/conversations', [
            'ride_id' => $this->ride->id,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'conversation' => ['id', 'ride_id', 'driver_id', 'rider_id'],
            ]);

        $this->assertDatabaseHas('conversation_threads', [
            'ride_id' => $this->ride->id,
            'rider_id' => $this->riderUser->id,
            'driver_id' => $this->driverUser->id,
        ]);
    }

    /**
     * Test IDOR protection for conversation thread creation/access.
     */
    public function test_intruder_cannot_create_or_access_conversation_thread()
    {
        Sanctum::actingAs($this->intruder, ['role:rider']);

        $response = $this->postJson('/api/v1/conversations', [
            'ride_id' => $this->ride->id,
        ]);

        $response->assertStatus(403);
    }
}
