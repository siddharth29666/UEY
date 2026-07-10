<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\DriverProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminDriverTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $driver;

    protected DriverProfile $driverProfile;

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
        $this->driver = User::create([
            'name' => 'Bob Driver',
            'phone' => '+447922222222',
            'email' => 'bob.driver@example.com',
            'password' => bcrypt('password123'),
            'role' => UserRole::DRIVER,
            'status' => UserStatus::PENDING_APPROVAL,
        ]);

        $this->driverProfile = DriverProfile::create([
            'user_id' => $this->driver->id,
            'license_number' => 'DL-123456',
            'license_expiry' => now()->addYears(2),
        ]);
    }

    /**
     * Test list drivers.
     */
    public function test_admin_can_list_drivers_with_filters()
    {
        Sanctum::actingAs($this->admin, ['role:admin']);

        $response = $this->getJson('/api/v1/admin/drivers?search=Bob');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'drivers')
            ->assertJsonPath('drivers.0.name', 'Bob Driver');
    }

    /**
     * Test get driver details.
     */
    public function test_admin_can_get_driver_details()
    {
        Sanctum::actingAs($this->admin, ['role:admin']);

        $response = $this->getJson("/api/v1/admin/drivers/{$this->driver->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('driver.id', $this->driver->id);
    }

    /**
     * Test approve driver onboarding.
     */
    public function test_admin_can_approve_driver_onboarding()
    {
        Sanctum::actingAs($this->admin, ['role:admin']);

        $response = $this->postJson("/api/v1/admin/drivers/{$this->driver->id}/approve");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertEquals(UserStatus::ACTIVE, $this->driver->refresh()->status);
    }

    /**
     * Test reject driver onboarding.
     */
    public function test_admin_can_reject_driver_onboarding()
    {
        Sanctum::actingAs($this->admin, ['role:admin']);

        $response = $this->postJson("/api/v1/admin/drivers/{$this->driver->id}/reject", [
            'rejection_reason' => 'License photo unclear',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertEquals(UserStatus::PENDING_APPROVAL, $this->driver->refresh()->status);
    }

    /**
     * Test block/unblock driver.
     */
    public function test_admin_can_block_and_unblock_driver()
    {
        Sanctum::actingAs($this->admin, ['role:admin']);

        // Block
        $response = $this->postJson("/api/v1/admin/drivers/{$this->driver->id}/block");
        $response->assertStatus(200);
        $this->assertEquals(UserStatus::SUSPENDED, $this->driver->refresh()->status);

        // Unblock
        $response = $this->postJson("/api/v1/admin/drivers/{$this->driver->id}/unblock");
        $response->assertStatus(200);
        $this->assertEquals(UserStatus::ACTIVE, $this->driver->refresh()->status);
    }

    /**
     * Test get driver documents list.
     */
    public function test_admin_can_get_driver_documents_list()
    {
        Sanctum::actingAs($this->admin, ['role:admin']);

        $response = $this->getJson("/api/v1/admin/drivers/{$this->driver->id}/documents");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'documents']);
    }
}
