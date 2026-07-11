<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\DriverProfile;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SchedulerDriverOfflineTest extends TestCase
{
    use RefreshDatabase;

    protected User $driver;

    protected DriverProfile $driverProfile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->driver = User::create([
            'name' => 'Bob Driver',
            'phone' => '+447911111111',
            'email' => 'bob@example.com',
            'password' => bcrypt('password'),
            'role' => UserRole::DRIVER,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->driverProfile = DriverProfile::create([
            'user_id' => $this->driver->id,
            'license_number' => 'DL-123456',
            'license_expiry' => now()->addYears(2),
            'is_online' => true,
            'last_seen_at' => now()->subMinutes(20),
        ]);

        Setting::updateOrCreate(
            ['key' => 'driver_offline_minutes'],
            ['value' => '15']
        );
    }

    public function test_driver_offline_command()
    {
        // Run command
        Artisan::call('app:driver-offline');

        $this->driverProfile->refresh();
        $this->assertFalse($this->driverProfile->is_online);

        // Assert scheduler log exists
        $this->assertDatabaseHas('scheduler_logs', [
            'command' => 'app:driver-offline',
            'status' => 'success',
        ]);
    }
}
