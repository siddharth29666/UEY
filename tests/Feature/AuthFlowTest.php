<?php

namespace Tests\Feature;

use App\Enums\OtpType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\OtpVerification;
use App\Models\User;
use App\Models\VehicleType;
use App\Models\DriverProfile;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthFlowTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test sending OTP code.
     */
    public function test_can_send_otp()
    {
        $response = $this->postJson('/api/v1/otp/send', [
            'phone' => '+447911123456',
            'type' => 'register',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'OTP sent successfully.',
            ]);

        $this->assertDatabaseHas('otp_verifications', [
            'phone' => '+447911123456',
            'type' => 'register',
        ]);
    }

    /**
     * Test OTP resend cooldown.
     */
    public function test_otp_resend_cooldown()
    {
        // 1. Send first OTP
        $this->postJson('/api/v1/otp/send', [
            'phone' => '+447911123456',
            'type' => 'register',
        ])->assertStatus(200);

        // 2. Send second OTP immediately (should fail due to 60s cooldown)
        $response = $this->postJson('/api/v1/otp/send', [
            'phone' => '+447911123456',
            'type' => 'register',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Please wait at least 60 seconds before requesting a new OTP.',
            ]);

        // 3. Travel 61 seconds in time
        Carbon::setTestNow(Carbon::now()->addSeconds(61));

        // 4. Send third OTP (should succeed now)
        $this->postJson('/api/v1/otp/send', [
            'phone' => '+447911123456',
            'type' => 'register',
        ])->assertStatus(200);

        Carbon::setTestNow(); // Reset time mock
    }

    /**
     * Test verifying OTP code.
     */
    public function test_can_verify_otp()
    {
        // 1. Pre-generate OTP record
        OtpVerification::create([
            'phone' => '+447911123456',
            'code' => '123456',
            'type' => OtpType::REGISTER,
            'expires_at' => Carbon::now()->addMinutes(5),
            'created_at' => Carbon::now(),
        ]);

        // 2. Call verify API
        $response = $this->postJson('/api/v1/otp/verify', [
            'phone' => '+447911123456',
            'code' => '123456',
            'type' => 'register',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'OTP verified successfully.',
            ]);

        $this->assertDatabaseHas('otp_verifications', [
            'phone' => '+447911123456',
            'code' => '123456',
            'type' => 'register',
            'verified_at' => Carbon::now()->toDateTimeString(),
        ]);
    }

    /**
     * Test OTP expiration check.
     */
    public function test_otp_expiry()
    {
        // 1. Pre-generate OTP record
        OtpVerification::create([
            'phone' => '+447911123456',
            'code' => '123456',
            'type' => OtpType::REGISTER,
            'expires_at' => Carbon::now()->addMinutes(5),
            'created_at' => Carbon::now(),
        ]);

        // 2. Travel 6 minutes in time (OTP expires at 5 minutes)
        Carbon::setTestNow(Carbon::now()->addMinutes(6));

        // 3. Call verify API (should fail)
        $response = $this->postJson('/api/v1/otp/verify', [
            'phone' => '+447911123456',
            'code' => '123456',
            'type' => 'register',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid or expired OTP code.',
            ]);

        Carbon::setTestNow(); // Reset time mock
    }

    /**
     * Test rider registration fails if phone is not verified.
     */
    public function test_rider_registration_fails_without_otp_verification()
    {
        $response = $this->postJson('/api/v1/register/rider', [
            'name' => 'John Rider',
            'email' => 'john.rider@example.com',
            'phone' => '+447911123456',
            'password' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Phone number has not been verified via OTP.',
            ]);
    }

    /**
     * Test rider registration succeeds if phone is verified.
     */
    public function test_rider_registration_succeeds_with_otp_verification()
    {
        // 1. Create verified OTP record
        OtpVerification::create([
            'phone' => '+447911123456',
            'code' => '123456',
            'type' => OtpType::REGISTER,
            'expires_at' => Carbon::now()->addMinutes(5),
            'verified_at' => Carbon::now(),
            'created_at' => Carbon::now(),
        ]);

        // 2. Register Rider
        $response = $this->postJson('/api/v1/register/rider', [
            'name' => 'John Rider',
            'email' => 'john.rider@example.com',
            'phone' => '+447911123456',
            'password' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Rider registered successfully.',
            ])
            ->assertJsonStructure(['token', 'user']);

        $this->assertDatabaseHas('users', [
            'phone' => '+447911123456',
            'role' => 'rider',
        ]);

        // Verify Wallet is initialized
        $this->assertDatabaseHas('wallets', [
            'balance' => 0.00,
        ]);
    }

    /**
     * Test driver registration.
     */
    public function test_driver_registration_succeeds_with_vehicle()
    {
        // 1. Create a Vehicle Type
        $vehicleType = VehicleType::create([
            'name' => 'Standard',
            'capacity' => 4,
            'base_fare' => 2.50,
            'per_km_rate' => 1.50,
            'per_minute_rate' => 0.20,
            'minimum_fare' => 5.00,
            'icon_url' => 'https://example.com/standard.png',
        ]);

        // 2. Create verified OTP record
        OtpVerification::create([
            'phone' => '+447911999999',
            'code' => '999999',
            'type' => OtpType::REGISTER,
            'expires_at' => Carbon::now()->addMinutes(5),
            'verified_at' => Carbon::now(),
            'created_at' => Carbon::now(),
        ]);

        // 3. Register Driver
        $response = $this->postJson('/api/v1/register/driver', [
            'name' => 'Bob Driver',
            'email' => 'bob@example.com',
            'phone' => '+447911999999',
            'password' => 'password123',
            'license_number' => 'DL-999888',
            'license_expiry' => Carbon::now()->addYear()->toDateString(),
            'vehicle_make' => 'Toyota',
            'vehicle_model' => 'Prius',
            'vehicle_year' => 2021,
            'vehicle_color' => 'Silver',
            'vehicle_plate' => 'ABC-999',
            'vehicle_type_id' => $vehicleType->id,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Driver registered successfully. Account is pending documents approval.',
            ])
            ->assertJsonStructure(['token', 'user']);

        $this->assertDatabaseHas('users', [
            'phone' => '+447911999999',
            'role' => 'driver',
            'status' => 'pending_approval',
        ]);

        $this->assertDatabaseHas('driver_profiles', [
            'license_number' => 'DL-999888',
        ]);

        $this->assertDatabaseHas('vehicles', [
            'plate_number' => 'ABC-999',
            'status' => 'pending',
        ]);
    }

    /**
     * Test login.
     */
    public function test_user_can_login()
    {
        $user = User::create([
            'name' => 'John Doe',
            'phone' => '+447911123456',
            'password' => Hash::make('password123'),
            'role' => UserRole::RIDER,
            'status' => UserStatus::ACTIVE,
        ]);

        $response = $this->postJson('/api/v1/login', [
            'phone' => '+447911123456',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Logged in successfully.',
            ])
            ->assertJsonStructure(['token', 'user']);
    }

    /**
     * Test logout.
     */
    public function test_user_can_logout()
    {
        $user = User::create([
            'name' => 'John Doe',
            'phone' => '+447911123456',
            'password' => Hash::make('password123'),
            'role' => UserRole::RIDER,
            'status' => UserStatus::ACTIVE,
        ]);

        $token = $user->createToken('test-token', ['role:rider'])->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/logout');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Logged out successfully.',
            ]);

        $this->assertEquals(0, $user->tokens()->count());
    }

    /**
     * Test token refresh.
     */
    public function test_user_can_refresh_token()
    {
        $user = User::create([
            'name' => 'John Doe',
            'phone' => '+447911123456',
            'password' => Hash::make('password123'),
            'role' => UserRole::RIDER,
            'status' => UserStatus::ACTIVE,
        ]);

        $token = $user->createToken('test-token', ['role:rider'])->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/token/refresh');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure(['token']);

        // The old token should be deleted, only 1 active token should remain (the new one)
        $this->assertEquals(1, $user->tokens()->count());
    }

    /**
     * Test getting user profile.
     */
    public function test_user_can_get_profile()
    {
        $user = User::create([
            'name' => 'John Doe',
            'phone' => '+447911123456',
            'password' => Hash::make('password123'),
            'role' => UserRole::RIDER,
            'status' => UserStatus::ACTIVE,
        ]);

        $token = $user->createToken('test-token', ['role:rider'])->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/profile');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure(['user' => ['id', 'name', 'phone', 'role', 'status']]);
    }

    /**
     * Test updating user profile.
     */
    public function test_user_can_update_profile()
    {
        $user = User::create([
            'name' => 'John Doe',
            'phone' => '+447911123456',
            'password' => Hash::make('password123'),
            'role' => UserRole::RIDER,
            'status' => UserStatus::ACTIVE,
        ]);

        $token = $user->createToken('test-token', ['role:rider'])->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson('/api/v1/profile', [
            'name' => 'John Updated',
            'email' => 'john.updated@example.com',
            'push_notifications' => false,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Profile updated successfully.',
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'John Updated',
            'email' => 'john.updated@example.com',
            'push_notifications' => false,
        ]);
    }

    /**
     * Test role-based protection via middleware ability.
     */
    public function test_role_middleware_protection()
    {
        $rider = User::create([
            'name' => 'John Rider',
            'phone' => '+447911123456',
            'password' => Hash::make('password123'),
            'role' => UserRole::RIDER,
            'status' => UserStatus::ACTIVE,
        ]);

        $driver = User::create([
            'name' => 'Bob Driver',
            'phone' => '+447911999999',
            'password' => Hash::make('password123'),
            'role' => UserRole::DRIVER,
            'status' => UserStatus::ACTIVE,
        ]);

        $riderToken = $rider->createToken('rider-token', ['role:rider'])->plainTextToken;
        $driverToken = $driver->createToken('driver-token', ['role:driver'])->plainTextToken;

        // 1. Driver tries to access rider dashboard (should be forbidden: 403)
        $response1 = $this->withHeaders([
            'Authorization' => 'Bearer ' . $driverToken,
        ])->getJson('/api/v1/rider/dashboard');

        $response1->assertStatus(403);

        // Reset auth state for the next request
        \Illuminate\Support\Facades\Auth::forgetGuards();

        // 2. Rider accesses rider dashboard (should succeed: 200)
        $response2 = $this->withHeaders([
            'Authorization' => 'Bearer ' . $riderToken,
        ])->getJson('/api/v1/rider/dashboard');

        $response2->assertStatus(200);
    }

    /**
     * Test unauthenticated request without JSON headers returns 401 JSON.
     */
    public function test_unauthenticated_api_request_returns_json_without_accept_header()
    {
        $response = $this->get('/api/v1/profile');

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Unauthenticated.',
            ]);
    }
}
