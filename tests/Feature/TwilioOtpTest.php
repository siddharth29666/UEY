<?php

namespace Tests\Feature;

use App\Models\OtpVerification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TwilioOtpTest extends TestCase
{
    use RefreshDatabase;

    public function test_otp_send_does_not_return_otp_in_api_response(): void
    {
        Http::fake([
            'verify.twilio.com/*' => Http::response([
                'sid' => 'VE123456',
                'status' => 'pending',
                'valid' => false,
            ], 200),
        ]);

        $response = $this->postJson('/api/v1/otp/send', [
            'phone' => '+447911999888',
            'type' => 'register',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'OTP sent successfully.',
            ]);

        // CRITICAL: Ensure OTP is NOT in response
        $this->assertArrayNotHasKey('otp', $response->json());

        // Internal verification record created
        $this->assertDatabaseHas('otp_verifications', [
            'phone' => '+447911999888',
            'type' => 'register',
            'code' => null,
        ]);
    }

    public function test_otp_resend_cooldown_enforced(): void
    {
        Http::fake([
            'verify.twilio.com/*' => Http::response(['sid' => 'VE123', 'status' => 'pending'], 200),
        ]);

        // First call
        $this->postJson('/api/v1/otp/send', [
            'phone' => '+447911999888',
            'type' => 'register',
        ])->assertStatus(200);

        // Immediate second call should fail with cooldown 422
        $response = $this->postJson('/api/v1/otp/send', [
            'phone' => '+447911999888',
            'type' => 'register',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Please wait at least 60 seconds before requesting a new OTP.',
            ]);
    }

    public function test_otp_verification_succeeds(): void
    {
        Http::fake([
            'verify.twilio.com/*' => Http::response([
                'sid' => 'VE123456',
                'status' => 'approved',
                'valid' => true,
            ], 200),
        ]);

        $response = $this->postJson('/api/v1/otp/verify', [
            'phone' => '+447911999888',
            'code' => '123456',
            'type' => 'register',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'OTP verified successfully.',
            ]);

        $this->assertDatabaseHas('otp_verifications', [
            'phone' => '+447911999888',
            'type' => 'register',
        ]);
    }

    public function test_otp_verification_fails_when_code_invalid(): void
    {
        Http::fake([
            'verify.twilio.com/*' => Http::response([
                'sid' => 'VE123456',
                'status' => 'pending',
                'valid' => false,
            ], 200),
        ]);

        // In testing environment with mock code other than 123456
        config(['services.twilio.account_sid' => 'AC_REAL_MOCK_SID']);
        config(['services.twilio.auth_token' => 'REAL_MOCK_TOKEN']);
        config(['services.twilio.verify_service_sid' => 'VA_REAL_MOCK_SID']);

        $response = $this->postJson('/api/v1/otp/verify', [
            'phone' => '+447911999888',
            'code' => '999999',
            'type' => 'register',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid or expired OTP code.',
            ]);
    }

    public function test_twilio_verify_placeholder_is_never_persisted_in_database(): void
    {
        Http::fake([
            'verify.twilio.com/*' => Http::response(['sid' => 'VE123', 'status' => 'pending'], 200),
        ]);

        $this->postJson('/api/v1/otp/send', [
            'phone' => '+918460935831',
            'type' => 'register',
        ])->assertStatus(200);

        // Assert TWILIO_VERIFY is NEVER stored in database
        $this->assertDatabaseMissing('otp_verifications', [
            'code' => 'TWILIO_VERIFY',
        ]);

        $record = OtpVerification::where('phone', '+918460935831')->first();
        $this->assertNotNull($record);
        $this->assertTrue(is_null($record->code) || strlen((string) $record->code) <= 6);
    }
}
