<?php

namespace Tests\Feature;

use App\Enums\OtpType;
use App\Models\OtpVerification;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SchedulerOtpCleanupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create expired OTP (created 15 mins ago)
        OtpVerification::create([
            'phone' => '+447911111111',
            'code' => '123456',
            'type' => OtpType::REGISTER,
            'expires_at' => now()->subMinutes(10),
            'created_at' => now()->subMinutes(15),
        ]);

        // Create active OTP (created just now)
        OtpVerification::create([
            'phone' => '+447922222222',
            'code' => '654321',
            'type' => OtpType::REGISTER,
            'expires_at' => now()->addMinutes(5),
            'created_at' => now(),
        ]);

        Setting::updateOrCreate(
            ['key' => 'otp_expiry_minutes'],
            ['value' => '5']
        );
    }

    public function test_cleanup_otp_command()
    {
        // Run command
        Artisan::call('app:cleanup-otp');

        // Expired OTP should be deleted
        $this->assertDatabaseMissing('otp_verifications', [
            'phone' => '+447911111111',
        ]);

        // Active OTP should still exist
        $this->assertDatabaseHas('otp_verifications', [
            'phone' => '+447922222222',
        ]);

        // Assert scheduler log exists
        $this->assertDatabaseHas('scheduler_logs', [
            'command' => 'app:cleanup-otp',
            'status' => 'success',
        ]);
    }
}
