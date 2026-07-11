<?php

namespace Tests\Feature;

use App\Enums\RideStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\VehicleStatus;
use App\Jobs\ProcessReferralBonusJob;
use App\Models\DriverProfile;
use App\Models\Referral;
use App\Models\Ride;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use App\Services\ReferralService;
use App\Services\RideLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ReferralBonusTest extends TestCase
{
    use RefreshDatabase;

    protected User $referrer;

    protected User $invitee;

    protected User $driver;

    protected DriverProfile $driverProfile;

    protected VehicleType $vehicleType;

    protected Ride $ride;

    protected function setUp(): void
    {
        parent::setUp();

        $this->referrer = User::create([
            'name' => 'Alice Referrer',
            'phone' => '+447911111111',
            'email' => 'alice@example.com',
            'password' => bcrypt('password'),
            'role' => UserRole::RIDER,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->invitee = User::create([
            'name' => 'Bob Invitee',
            'phone' => '+447922222222',
            'email' => 'bob@example.com',
            'password' => bcrypt('password'),
            'role' => UserRole::RIDER,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->driver = User::create([
            'name' => 'Charlie Driver',
            'phone' => '+447933333333',
            'email' => 'charlie@example.com',
            'password' => bcrypt('password'),
            'role' => UserRole::DRIVER,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->driverProfile = DriverProfile::create([
            'user_id' => $this->driver->id,
            'license_number' => 'DL-999999',
            'license_expiry' => now()->addYears(2),
            'is_online' => true,
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

        Vehicle::create([
            'driver_profile_id' => $this->driverProfile->id,
            'vehicle_type_id' => $this->vehicleType->id,
            'make' => 'Toyota',
            'model' => 'Prius',
            'year' => 2021,
            'color' => 'White',
            'plate_number' => 'PL-9999',
            'status' => VehicleStatus::APPROVED,
        ]);

        // Apply referral code
        app(ReferralService::class)->applyReferralCode($this->invitee, $this->referrer->referral_code);

        // Create ride in progress
        $this->ride = Ride::create([
            'rider_id' => $this->invitee->id,
            'driver_profile_id' => $this->driverProfile->id,
            'vehicle_type_id' => $this->vehicleType->id,
            'pickup_address' => 'Start Point',
            'pickup_latitude' => 51.5074,
            'pickup_longitude' => -0.1278,
            'destination_address' => 'End Point',
            'destination_latitude' => 51.5204,
            'destination_longitude' => -0.1482,
            'status' => RideStatus::IN_PROGRESS,
            'otp' => '123456',
            'estimated_distance' => 5.0,
            'estimated_duration' => 10,
            'estimated_fare' => 20.0,
            'payment_method' => 'cash',
        ]);
    }

    public function test_first_ride_completion_triggers_referral_job()
    {
        Queue::fake();

        // Complete the ride
        app(RideLifecycleService::class)->updateStatus(
            $this->ride,
            'completed',
            [
                'actual_distance' => 5.0,
                'actual_duration' => 10,
            ],
            $this->driver
        );

        $this->invitee->refresh();
        $this->assertTrue($this->invitee->first_ride_completed);

        $referral = Referral::where('referred_user_id', $this->invitee->id)->first();
        $this->assertEquals('completed', $referral->status);
        $this->assertNotNull($referral->first_ride_completed_at);

        Queue::assertPushed(ProcessReferralBonusJob::class, function ($job) use ($referral) {
            return $job->referral->id === $referral->id && $job->queue === 'default';
        });
    }

    public function test_issue_referral_bonuses_credits_wallets()
    {
        $referral = Referral::where('referred_user_id', $this->invitee->id)->first();

        // Mark completed to simulate first ride completed detection
        $this->invitee->update(['first_ride_completed' => true]);
        $referral->update([
            'status' => 'completed',
            'first_ride_completed_at' => now(),
        ]);

        // Process the rewards
        app(ReferralService::class)->issueReferralBonuses($referral);

        $referral->refresh();
        $this->assertNotNull($referral->rewarded_at);

        // Referrer Wallet balance credit (+10.00)
        $referrerWallet = $this->referrer->wallet;
        $this->assertNotNull($referrerWallet);
        $this->assertEquals(10.00, (float) $referrerWallet->balance);

        // Invitee Wallet balance credit (+5.00)
        $inviteeWallet = $this->invitee->wallet;
        $this->assertNotNull($inviteeWallet);
        $this->assertEquals(5.00, (float) $inviteeWallet->balance);

        // Check wallet transaction logs
        $this->assertDatabaseHas('wallet_transactions', [
            'wallet_id' => $referrerWallet->id,
            'transaction_type' => 'referral_bonus',
            'amount' => 10.00,
            'type' => 'credit',
        ]);

        $this->assertDatabaseHas('wallet_transactions', [
            'wallet_id' => $inviteeWallet->id,
            'transaction_type' => 'referral_bonus',
            'amount' => 5.00,
            'type' => 'credit',
        ]);

        // Check audit log logs
        $this->assertDatabaseHas('audit_logs', [
            'module' => 'referrals',
            'action' => 'credit_referrer_bonus',
        ]);

        // Check notification logs
        $this->assertDatabaseHas('notification_logs', [
            'user_id' => $this->referrer->id,
            'type' => 'referral_bonus_received',
        ]);
    }
}
