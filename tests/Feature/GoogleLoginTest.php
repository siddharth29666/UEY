<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_google_redirect_endpoint(): void
    {
        $response = $this->getJson('/api/v1/auth/google/redirect?role=rider');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure(['url']);

        $this->assertStringContainsString('accounts.google.com', $response->json('url'));
    }

    public function test_google_callback_registers_new_user(): void
    {
        $response = $this->postJson('/api/v1/auth/google/callback', [
            'code' => 'valid_google_code_123',
            'role' => 'rider',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Google authentication successful.',
            ])
            ->assertJsonStructure([
                'data' => ['user', 'access_token', 'token_type'],
            ]);

        $this->assertDatabaseHas('users', [
            'role' => 'rider',
            'auth_provider' => 'google',
        ]);
    }

    public function test_google_callback_authenticates_existing_user_by_google_id(): void
    {
        $user = User::create([
            'name' => 'Existing Google User',
            'email' => 'google.existing@example.com',
            'google_id' => 'google_mock_'.substr(md5('valid_google_code_456'), 0, 12),
            'auth_provider' => 'google',
            'role' => UserRole::RIDER,
            'status' => UserStatus::ACTIVE,
            'password' => Hash::make('secret'),
        ]);

        $response = $this->postJson('/api/v1/auth/google/callback', [
            'code' => 'valid_google_code_456',
            'role' => 'rider',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertEquals($user->id, $response->json('data.user.id'));
    }

    public function test_google_callback_links_existing_user_by_email(): void
    {
        $user = User::create([
            'name' => 'Email User',
            'email' => 'google_user_'.substr(md5('valid_google_code_789'), 0, 6).'@example.com',
            'auth_provider' => 'email',
            'role' => UserRole::RIDER,
            'status' => UserStatus::ACTIVE,
            'password' => Hash::make('secret'),
        ]);

        $response = $this->postJson('/api/v1/auth/google/callback', [
            'code' => 'valid_google_code_789',
            'role' => 'rider',
        ]);

        $response->assertStatus(200);

        $user->refresh();
        $this->assertEquals('google', $user->auth_provider);
        $this->assertNotNull($user->google_id);
    }
}
