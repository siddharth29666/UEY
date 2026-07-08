<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Jobs\BroadcastNotificationJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminNotificationTest extends TestCase
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
     * Test broadcasting system announcement.
     */
    public function test_admin_can_broadcast_announcements_to_users()
    {
        Queue::fake();
        Sanctum::actingAs($this->admin, ['role:admin']);

        $response = $this->postJson('/api/v1/admin/notifications/broadcast', [
            'target' => 'all_users',
            'title' => 'Maintenance Alert',
            'body' => 'We will perform scheduled updates tonight.',
            'category' => 'system',
            'priority' => 'high',
            'channels' => ['push', 'database'],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        Queue::assertPushed(BroadcastNotificationJob::class);

        // Assert audit log exists
        $this->assertDatabaseHas('audit_logs', [
            'admin_id' => $this->admin->id,
            'action' => 'notification_broadcast',
            'module' => 'notifications',
        ]);
    }
}
