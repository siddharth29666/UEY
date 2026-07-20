<?php

namespace Tests\Feature;

use App\Enums\NotificationType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Events\ReferralApplied;
use App\Models\Referral;
use App\Models\User;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RegistrationReferralTest extends TestCase
{
    use RefreshDatabase;

    protected User $referrer;
    protected VehicleType $vehicleType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->referrer = User::create([
            'name' => 'Active Referrer',
            'email' => 'referrer@example.com',
            'phone' => '+447911222222',
            'password' => bcrypt('password'),
            'role' => UserRole::RIDER,
            'status' => UserStatus::ACTIVE,
            'referral_code' => 'REFR1234',
        ]);

        $this->vehicleType = VehicleType::create([
            'name' => 'Economy Class',
            'capacity' => 4,
            'base_fare' => 2.50,
            'per_km_rate' => 1.20,
            'per_minute_rate' => 0.30,
            'minimum_fare' => 5.00,
            'commission_percentage' => 15.00,
            'icon_url' => 'https://example.com/icon.png',
            'active' => true,
        ]);
    }

    public function test_rider_registration_with_valid_referral_code()
    {
        Event::fake([ReferralApplied::class]);

        // Mock OTP verification
        DB::table('otp_verifications')->insert([
            'phone' => '+447911111111',
            'code' => '123456',
            'type' => 'register',
            'verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/register/rider', [
            'name' => 'Referred Rider',
            'email' => 'referred.rider@example.com',
            'phone' => '+447911111111',
            'password' => 'password123',
            'referral_code' => 'REFR1234',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);

        // Check relationship mapped
        $user = User::where('phone', '+447911111111')->first();
        $this->assertNotNull($user);
        $this->assertEquals($this->referrer->id, $user->referred_by);

        // Check pending referral created
        $this->assertDatabaseHas('referrals', [
            'referrer_id' => $this->referrer->id,
            'referred_user_id' => $user->id,
            'referral_code' => 'REFR1234',
            'status' => 'pending',
        ]);

        // Assert ReferralApplied event fired for both users
        Event::assertDispatched(ReferralApplied::class, function ($event) use ($user) {
            return $event->user->id === $user->id;
        });

        Event::assertDispatched(ReferralApplied::class, function ($event) {
            return $event->user->id === $this->referrer->id;
        });
    }

    public function test_driver_registration_with_valid_referral_code()
    {
        Event::fake([ReferralApplied::class]);

        DB::table('otp_verifications')->insert([
            'phone' => '+447911999888',
            'code' => '123456',
            'type' => 'register',
            'verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/register/driver', [
            'name' => 'Referred Driver',
            'email' => 'referred.driver@example.com',
            'phone' => '+447911999888',
            'password' => 'password123',
            'license_number' => 'DL-888999',
            'license_expiry' => '2027-12-31',
            'vehicle_make' => 'Toyota',
            'vehicle_model' => 'Camry',
            'vehicle_year' => 2021,
            'vehicle_color' => 'Black',
            'vehicle_plate' => 'WP-9999',
            'vehicle_type_id' => $this->vehicleType->id,
            'referral_code' => 'REFR1234',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);

        $user = User::where('phone', '+447911999888')->first();
        $this->assertNotNull($user);
        $this->assertEquals($this->referrer->id, $user->referred_by);

        $this->assertDatabaseHas('referrals', [
            'referrer_id' => $this->referrer->id,
            'referred_user_id' => $user->id,
            'status' => 'pending',
        ]);
    }

    public function test_registration_fails_with_invalid_referral_code()
    {
        // Mock OTP verification
        DB::table('otp_verifications')->insert([
            'phone' => '+447911111111',
            'code' => '123456',
            'type' => 'register',
            'verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/register/rider', [
            'name' => 'Referred Rider',
            'email' => 'referred.rider@example.com',
            'phone' => '+447911111111',
            'password' => 'password123',
            'referral_code' => 'INVALID9', // Non-existent
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid referral code.',
            ]);
    }

    public function test_registration_fails_with_self_referral()
    {
        // Mock OTP verification for referrer phone trying to register with their own code
        DB::table('otp_verifications')->insert([
            'phone' => $this->referrer->phone,
            'code' => '123456',
            'type' => 'register',
            'verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/register/rider', [
            'name' => 'Self Referring Rider',
            'email' => $this->referrer->email,
            'phone' => $this->referrer->phone,
            'password' => 'password123',
            'referral_code' => 'REFR1234',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid referral code.',
            ]);
    }

    public function test_referral_apply_endpoint_is_backward_compatible_and_idempotent()
    {
        $newRider = User::create([
            'name' => 'Already Referred Rider',
            'email' => 'already.referred@example.com',
            'phone' => '+447911111222',
            'password' => bcrypt('password'),
            'role' => UserRole::RIDER,
            'status' => UserStatus::ACTIVE,
            'referred_by' => $this->referrer->id,
        ]);

        Referral::create([
            'referrer_id' => $this->referrer->id,
            'referred_user_id' => $newRider->id,
            'referral_code' => 'REFR1234',
            'status' => 'pending',
            'referrer_bonus' => 10.00,
            'referred_bonus' => 5.00,
        ]);

        Sanctum::actingAs($newRider);

        // Call POST /api/v1/referrals/apply with the same code
        $response = $this->postJson('/api/v1/referrals/apply', [
            'referral_code' => 'REFR1234',
        ]);

        // Should return success true, referral already applied message
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Referral already applied.',
            ]);
    }
}
