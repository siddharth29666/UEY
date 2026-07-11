<?php

namespace Tests\Feature;

use App\Enums\RideStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Ride;
use App\Models\Setting;
use App\Models\User;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SchedulerRideTimeoutTest extends TestCase
{
    use RefreshDatabase;

    protected User $rider;

    protected VehicleType $vehicleType;

    protected Ride $ride;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rider = User::create([
            'name' => 'Alice Rider',
            'phone' => '+447911111111',
            'email' => 'alice@example.com',
            'password' => bcrypt('password'),
            'role' => UserRole::RIDER,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->vehicleType = VehicleType::create([
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
            'rider_id' => $this->rider->id,
            'vehicle_type_id' => $this->vehicleType->id,
            'pickup_address' => 'Start Point',
            'pickup_latitude' => 51.5074,
            'pickup_longitude' => -0.1278,
            'destination_address' => 'End Point',
            'destination_latitude' => 51.5204,
            'destination_longitude' => -0.1482,
            'status' => RideStatus::PENDING,
            'otp' => '123456',
            'estimated_distance' => 5.0,
            'estimated_duration' => 10,
            'estimated_fare' => 20.0,
            'payment_method' => 'cash',
        ]);

        $this->ride->created_at = now()->subMinutes(15);
        $this->ride->save();

        Setting::updateOrCreate(
            ['key' => 'ride_timeout_minutes'],
            ['value' => '10']
        );
    }

    public function test_expire_pending_rides_command()
    {
        // Run command
        Artisan::call('app:expire-pending-rides');

        $this->ride->refresh();
        $this->assertEquals(RideStatus::CANCELLED, $this->ride->status);
        $this->assertEquals('Ride request timed out.', $this->ride->cancel_reason);

        // Assert scheduler log exists
        $this->assertDatabaseHas('scheduler_logs', [
            'command' => 'app:expire-pending-rides',
            'status' => 'success',
        ]);
    }
}
