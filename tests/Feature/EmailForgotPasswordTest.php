<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Mail\PasswordResetOtpMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailForgotPasswordTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Reset Tester',
            'email' => 'reset.tester@example.com',
            'phone' => '+447911999111',
            'password' => Hash::make('OldPassword123!'),
            'role' => UserRole::RIDER,
            'status' => UserStatus::ACTIVE,
        ]);
    }

    public function test_forgot_password_sends_email_otp_without_returning_code(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'reset.tester@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Password reset OTP has been sent to your email address.',
            ]);

        // CRITICAL: Ensure OTP is NOT in response
        $this->assertArrayNotHasKey('otp', $response->json());

        // Assert Mailable was sent
        Mail::assertSent(PasswordResetOtpMail::class, function ($mail) {
            return $mail->hasTo('reset.tester@example.com');
        });

        // Assert hashed token exists in DB
        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => 'reset.tester@example.com',
        ]);
    }

    public function test_verify_forgot_password_otp_succeeds(): void
    {
        DB::table('password_reset_tokens')->insert([
            'email' => 'reset.tester@example.com',
            'token' => Hash::make('654321'),
            'created_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/auth/forgot-password/verify', [
            'email' => 'reset.tester@example.com',
            'otp' => '654321',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Password reset OTP verified successfully.',
            ]);
    }

    public function test_reset_password_with_valid_otp_succeeds(): void
    {
        DB::table('password_reset_tokens')->insert([
            'email' => 'reset.tester@example.com',
            'token' => Hash::make('654321'),
            'created_at' => now(),
        ]);

        // Create an existing token to ensure it gets revoked
        $this->user->createToken('test_token');
        $this->assertCount(1, $this->user->tokens);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'reset.tester@example.com',
            'otp' => '654321',
            'password' => 'NewSecretPass123!',
            'password_confirmation' => 'NewSecretPass123!',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Password reset successfully.',
            ]);

        // User password updated
        $this->user->refresh();
        $this->assertTrue(Hash::check('NewSecretPass123!', $this->user->password));

        // Token revoked & reset token deleted
        $this->assertCount(0, $this->user->tokens);
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => 'reset.tester@example.com',
        ]);
    }

    public function test_reset_password_fails_with_invalid_otp(): void
    {
        DB::table('password_reset_tokens')->insert([
            'email' => 'reset.tester@example.com',
            'token' => Hash::make('654321'),
            'created_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'reset.tester@example.com',
            'otp' => '000000',
            'password' => 'NewSecretPass123!',
            'password_confirmation' => 'NewSecretPass123!',
        ]);

        $response->assertStatus(422);
    }
}
