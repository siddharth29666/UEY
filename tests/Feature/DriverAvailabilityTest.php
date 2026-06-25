<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\DriverProfile;
use App\Models\User;
use App\Services\DriverLocationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class DriverAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    protected User $driverUser;
    protected DriverProfile $driverProfile;

    protected function setUp(): void
    {
        parent::setUp();

        // Create driver user
        $this->driverUser = User::create([
            'name' => 'Bob Driver',
            'phone' => '+447911999999',
            'password' => Hash::make('password123'),
            'role' => UserRole::DRIVER,
            'status' => UserStatus::PENDING_APPROVAL,
        ]);

        $this->driverProfile = DriverProfile::create([
            'user_id' => $this->driverUser->id,
            'license_number' => 'DL-999888',
            'license_expiry' => Carbon::now()->addYear(),
            'rating' => 4.85,
            'acceptance_rate' => 97.20,
            'ontime_rate' => 98.90,
            'current_latitude' => 51.5074,
            'current_longitude' => -0.1278,
            'bearing' => 90.0,
        ]);
    }

    /**
     * Test an unapproved (pending_approval) driver cannot go online.
     */
    public function test_unapproved_driver_cannot_go_online()
    {
        $token = $this->driverUser->createToken('test-token', ['role:driver'])->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/driver/status', [
            'is_online' => true,
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Only active approved drivers can go online.',
            ]);

        $this->driverProfile->refresh();
        $this->assertFalse($this->driverProfile->is_online);
    }

    /**
     * Test an approved (active) driver can go online.
     */
    public function test_approved_driver_can_go_online()
    {
        Redis::spy();

        // Set user to active
        $this->driverUser->update(['status' => UserStatus::ACTIVE]);

        $token = $this->driverUser->createToken('test-token', ['role:driver'])->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/driver/status', [
            'is_online' => true,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Driver status updated successfully.',
                'is_online' => true,
            ]);

        $this->driverProfile->refresh();
        $this->assertTrue($this->driverProfile->is_online);
        $this->assertNotNull($this->driverProfile->last_seen_at);

        // Assert Redis geoadd was called
        Redis::shouldHaveReceived('geoadd')
            ->once()
            ->with(
                'drivers:locations',
                (float) $this->driverProfile->current_longitude,
                (float) $this->driverProfile->current_latitude,
                (string) $this->driverProfile->id
            );
    }

    /**
     * Test an online driver can go offline.
     */
    public function test_online_driver_can_go_offline()
    {
        Redis::spy();

        $this->driverUser->update(['status' => UserStatus::ACTIVE]);
        $this->driverProfile->update(['is_online' => true]);

        $token = $this->driverUser->createToken('test-token', ['role:driver'])->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/driver/status', [
            'is_online' => false,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Driver status updated successfully.',
                'is_online' => false,
            ]);

        $this->driverProfile->refresh();
        $this->assertFalse($this->driverProfile->is_online);

        // Assert Redis zrem was called
        Redis::shouldHaveReceived('zrem')
            ->once()
            ->with('drivers:locations', (string) $this->driverProfile->id);
    }

    /**
     * Test driver can update location.
     */
    public function test_driver_can_update_location()
    {
        Redis::spy();

        $this->driverUser->update(['status' => UserStatus::ACTIVE]);
        $this->driverProfile->update(['is_online' => true]);

        $token = $this->driverUser->createToken('test-token', ['role:driver'])->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/driver/location', [
            'current_latitude' => 51.5204,
            'current_longitude' => -0.1482,
            'bearing' => 120.5,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Driver location updated successfully.',
            ]);

        $this->driverProfile->refresh();
        $this->assertEquals(51.5204, (float) $this->driverProfile->current_latitude);
        $this->assertEquals(-0.1482, (float) $this->driverProfile->current_longitude);
        $this->assertEquals(120.5, (float) $this->driverProfile->bearing);
        $this->assertNotNull($this->driverProfile->last_located_at);
        $this->assertNotNull($this->driverProfile->last_seen_at);

        // Assert Redis geoadd was called with new coordinates
        Redis::shouldHaveReceived('geoadd')
            ->once()
            ->with(
                'drivers:locations',
                -0.1482,
                51.5204,
                (string) $this->driverProfile->id
            );
    }

    /**
     * Test location update only saves to database if driver is offline.
     */
    public function test_offline_location_update_does_not_sync_redis()
    {
        Redis::spy();

        $this->driverUser->update(['status' => UserStatus::ACTIVE]);
        $this->driverProfile->update(['is_online' => false]);

        $token = $this->driverUser->createToken('test-token', ['role:driver'])->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/driver/location', [
            'current_latitude' => 51.5204,
            'current_longitude' => -0.1482,
            'bearing' => 120.5,
        ]);

        $response->assertStatus(200);

        $this->driverProfile->refresh();
        $this->assertEquals(51.5204, (float) $this->driverProfile->current_latitude);
        $this->assertEquals(-0.1482, (float) $this->driverProfile->current_longitude);

        // Redis geoadd should NOT be called
        Redis::shouldNotHaveReceived('geoadd');
    }

    /**
     * Test retrieving driver dashboard details.
     */
    public function test_driver_dashboard()
    {
        $token = $this->driverUser->createToken('test-token', ['role:driver'])->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/driver/dashboard');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'dashboard' => [
                    'driver_profile_id' => $this->driverProfile->id,
                    'is_online' => false,
                    'rating' => 4.85,
                    'acceptance_rate' => 97.20,
                    'ontime_rate' => 98.90,
                    'completed_rides_count' => 0,
                    'earnings_summary' => [
                        'today' => 0.00,
                        'this_week' => 0.00,
                        'total' => 0.00,
                    ],
                    'profile' => [
                        'name' => 'Bob Driver',
                        'phone' => '+447911999999',
                        'email' => null,
                        'avatar_url' => null,
                    ]
                ]
            ]);
    }

    /**
     * Test getNearbyDrivers service method.
     */
    public function test_get_nearby_drivers_service_method()
    {
        // Mock Redis executeRaw for GEOSEARCH command using Mockery type matching
        Redis::shouldReceive('executeRaw')
            ->once()
            ->with(\Mockery::type('array'))
            ->andReturn([
                ['1', '1.2500', ['-0.1250', '51.5060']]
            ]);

        $service = new DriverLocationService();
        $results = $service->getNearbyDrivers(51.5074, -0.1278, 5);

        $this->assertCount(1, $results);
        $this->assertEquals(1, $results[0]['driver_profile_id']);
        $this->assertEquals(1.2500, $results[0]['distance']);
        $this->assertEquals(51.5060, $results[0]['latitude']);
        $this->assertEquals(-0.1250, $results[0]['longitude']);
    }
}
