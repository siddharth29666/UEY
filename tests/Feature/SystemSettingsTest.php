<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SystemSettingsTest extends TestCase
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
            'password' => bcrypt('password123'),
            'role' => UserRole::ADMIN,
            'status' => UserStatus::ACTIVE,
        ]);
    }

    /**
     * Test get and update system settings.
     */
    public function test_admin_can_retrieve_and_update_system_settings()
    {
        Sanctum::actingAs($this->admin, ['role:admin']);

        // 1. Get
        $response = $this->getJson('/api/v1/admin/settings');
        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        // 2. Update
        $response = $this->putJson('/api/v1/admin/settings', [
            'platform_commission' => 15.00,
            'currency' => 'USD',
            'distance_unit' => 'km',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('settings.platform_commission', '15');

        // Assert cached values
        $settingService = app(SettingService::class);
        $this->assertEquals('15', $settingService->get('platform_commission'));
        $this->assertEquals('USD', $settingService->get('currency'));

        // Assert audit log exists
        $this->assertDatabaseHas('audit_logs', [
            'admin_id' => $this->admin->id,
            'action' => 'settings_update',
            'module' => 'settings',
        ]);
    }

    /**
     * Test manual settings cache refresh.
     */
    public function test_admin_can_manually_refresh_settings_cache()
    {
        Sanctum::actingAs($this->admin, ['role:admin']);

        $response = $this->postJson('/api/v1/admin/settings/cache-refresh');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }
}
