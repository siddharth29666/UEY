<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminRiderTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $rider;

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

        $this->rider = User::create([
            'name' => 'John Rider',
            'phone' => '+447911111111',
            'email' => 'john.rider@example.com',
            'password' => bcrypt('password123'),
            'role' => UserRole::RIDER,
            'status' => UserStatus::ACTIVE,
        ]);
    }

    /**
     * Test list riders.
     */
    public function test_admin_can_list_riders_with_filters()
    {
        Sanctum::actingAs($this->admin, ['role:admin']);

        $response = $this->getJson('/api/v1/admin/riders?search=John&status=active');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'riders')
            ->assertJsonPath('riders.0.name', 'John Rider');
    }

    /**
     * Test get rider details.
     */
    public function test_admin_can_get_rider_details()
    {
        Sanctum::actingAs($this->admin, ['role:admin']);

        $response = $this->getJson("/api/v1/admin/riders/{$this->rider->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('rider.id', $this->rider->id);
    }

    /**
     * Test update rider profile.
     */
    public function test_admin_can_update_rider_profile()
    {
        Sanctum::actingAs($this->admin, ['role:admin']);

        $response = $this->putJson("/api/v1/admin/riders/{$this->rider->id}", [
            'name' => 'John Rider Updated',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('rider.name', 'John Rider Updated');
    }

    /**
     * Test block/unblock rider.
     */
    public function test_admin_can_block_and_unblock_rider()
    {
        Sanctum::actingAs($this->admin, ['role:admin']);

        // Block
        $response = $this->postJson("/api/v1/admin/riders/{$this->rider->id}/block");
        $response->assertStatus(200);
        $this->assertEquals(UserStatus::SUSPENDED, $this->rider->refresh()->status);

        // Unblock
        $response = $this->postJson("/api/v1/admin/riders/{$this->rider->id}/unblock");
        $response->assertStatus(200);
        $this->assertEquals(UserStatus::ACTIVE, $this->rider->refresh()->status);
    }

    /**
     * Test delete rider (soft delete).
     */
    public function test_admin_can_delete_rider()
    {
        Sanctum::actingAs($this->admin, ['role:admin']);

        $response = $this->deleteJson("/api/v1/admin/riders/{$this->rider->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('users', ['id' => $this->rider->id]);
    }
}
