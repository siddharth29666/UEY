<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Notifications\PasswordResetNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AccountManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test user
        $this->user = User::create([
            'name' => 'Alice Rider',
            'email' => 'alice@example.com',
            'phone' => '+447911111111',
            'password' => Hash::make('password123'),
            'role' => UserRole::RIDER,
            'status' => UserStatus::ACTIVE,
        ]);
    }

    /**
     * Test requesting a password reset OTP with a valid email.
     */
    public function test_user_can_request_password_reset_otp()
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'alice@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Password reset OTP sent successfully.',
            ]);

        // Assert a notification was sent to the user
        Notification::assertSentTo($this->user, PasswordResetNotification::class, function ($notification) {
            return !empty($notification);
        });

        // Assert record exists in password_reset_tokens
        $this->assertTrue(
            DB::table('password_reset_tokens')->where('email', 'alice@example.com')->exists()
        );
    }

    /**
     * Test requesting a password reset OTP with an invalid email fails.
     */
    public function test_request_password_reset_with_invalid_email_fails()
    {
        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'unknown@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /**
     * Test resetting password successfully with valid email and OTP.
     */
    public function test_user_can_reset_password_with_valid_otp()
    {
        Notification::fake();

        // 1. Request OTP
        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'alice@example.com',
        ]);

        // 2. Fetch the OTP from notification or database
        $tokenRecord = DB::table('password_reset_tokens')->where('email', 'alice@example.com')->first();
        $this->assertNotNull($tokenRecord);

        // Since token is hashed in DB, we'll simulate verification using a plain text OTP.
        // But since we faked the notification, we can grab the OTP code from the sent notification!
        $otpCode = '';
        Notification::assertSentTo($this->user, PasswordResetNotification::class, function ($notification) use (&$otpCode) {
            // Reflect/access the protected otp property
            $reflector = new \ReflectionClass($notification);
            $property = $reflector->getProperty('otp');
            $property->setAccessible(true);
            $otpCode = $property->getValue($notification);
            return true;
        });

        $this->assertNotEmpty($otpCode);

        // 3. Reset password
        $response = $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'alice@example.com',
            'otp' => $otpCode,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Password reset successfully.',
            ]);

        // 4. Verify password was updated
        $this->assertTrue(Hash::check('newpassword123', $this->user->fresh()->password));

        // 5. Assert OTP was invalidated (deleted)
        $this->assertFalse(
            DB::table('password_reset_tokens')->where('email', 'alice@example.com')->exists()
        );
    }

    /**
     * Test resetting password fails with incorrect OTP.
     */
    public function test_reset_password_fails_with_invalid_otp()
    {
        // 1. Create a dummy token in the database
        DB::table('password_reset_tokens')->insert([
            'email' => 'alice@example.com',
            'token' => Hash::make('123456'),
            'created_at' => now(),
        ]);

        // 2. Attempt with wrong OTP
        $response = $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'alice@example.com',
            'otp' => '654321', // wrong
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['otp']);
    }

    /**
     * Test resetting password fails with an expired OTP.
     */
    public function test_reset_password_fails_with_expired_otp()
    {
        // 1. Create an expired token (created 15 minutes ago)
        DB::table('password_reset_tokens')->insert([
            'email' => 'alice@example.com',
            'token' => Hash::make('123456'),
            'created_at' => now()->subMinutes(15),
        ]);

        // 2. Attempt reset
        $response = $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'alice@example.com',
            'otp' => '123456',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['otp']);
    }

    /**
     * Test account deletion completes successfully with correct password.
     */
    public function test_user_can_delete_account_with_correct_password()
    {
        $token = $this->user->createToken('test-token', ['role:rider'])->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->deleteJson('/api/v1/profile/delete-account', [
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Account deleted successfully.',
            ]);

        // Assert user is soft-deleted
        $this->assertSoftDeleted('users', [
            'id' => $this->user->id,
        ]);
    }

    /**
     * Test account deletion fails with incorrect password.
     */
    public function test_account_deletion_fails_with_incorrect_password()
    {
        $token = $this->user->createToken('test-token', ['role:rider'])->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->deleteJson('/api/v1/profile/delete-account', [
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid password.',
            ]);

        // Assert user is NOT deleted
        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'deleted_at' => null,
        ]);
    }

    /**
     * Test that a deleted user cannot authenticate or log in again.
     */
    public function test_deleted_user_cannot_authenticate()
    {
        // 1. Soft delete the user
        $this->user->delete();

        // 2. Attempt login
        $response = $this->postJson('/api/v1/login', [
            'phone' => '+447911111111',
            'password' => 'password123',
        ]);

        // Standard login failure as the soft-deleted user is excluded from query
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }
}
