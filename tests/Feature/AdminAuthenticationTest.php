<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Alice Admin',
            'phone' => '+447999999999',
            'email' => 'alice.admin@example.com',
            'password' => Hash::make('password123'),
            'role' => UserRole::ADMIN,
            'status' => UserStatus::ACTIVE,
        ]);
    }

    /**
     * Test successful login.
     */
    public function test_admin_can_login_with_correct_credentials()
    {
        $response = $this->postJson('/api/v1/admin/login', [
            'phone' => '+447999999999',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'token',
                'user' => [
                    'id',
                    'name',
                    'email',
                    'phone',
                    'role',
                ],
            ]);

        $this->assertNotNull($this->admin->refresh()->last_login_at);

        // Assert audit log exists
        $this->assertDatabaseHas('audit_logs', [
            'admin_id' => $this->admin->id,
            'action' => 'admin_login',
            'module' => 'auth',
        ]);
    }

    /**
     * Test login fails with invalid credentials.
     */
    public function test_login_fails_for_non_admin_roles()
    {
        $rider = User::create([
            'name' => 'Charlie Rider',
            'phone' => '+447911111111',
            'password' => Hash::make('password123'),
            'role' => UserRole::RIDER,
            'status' => UserStatus::ACTIVE,
        ]);

        $response = $this->postJson('/api/v1/admin/login', [
            'phone' => '+447911111111',
            'password' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }

    /**
     * Test admin logout.
     */
    public function test_admin_can_logout()
    {
        Sanctum::actingAs($this->admin, ['role:admin']);

        $response = $this->postJson('/api/v1/admin/logout');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('audit_logs', [
            'admin_id' => $this->admin->id,
            'action' => 'admin_logout',
            'module' => 'auth',
        ]);
    }

    /**
     * Test updating profile.
     */
    public function test_admin_can_update_profile()
    {
        Sanctum::actingAs($this->admin, ['role:admin']);

        $response = $this->putJson('/api/v1/admin/profile', [
            'name' => 'Alice Modified',
            'email' => 'alice.new@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('user.name', 'Alice Modified');

        $this->assertDatabaseHas('audit_logs', [
            'admin_id' => $this->admin->id,
            'action' => 'profile_update',
            'module' => 'profile',
        ]);
    }

    /**
     * Test changing password.
     */
    public function test_admin_can_change_password()
    {
        Sanctum::actingAs($this->admin, ['role:admin']);

        $response = $this->putJson('/api/v1/admin/change-password', [
            'current_password' => 'password123',
            'new_password' => 'newpassword123',
            'new_password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertTrue(Hash::check('newpassword123', $this->admin->refresh()->password));

        $this->assertDatabaseHas('audit_logs', [
            'admin_id' => $this->admin->id,
            'action' => 'password_change',
            'module' => 'profile',
        ]);
    }
}
