<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected AuditLog $auditLog;

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

        $this->auditLog = AuditLog::create([
            'admin_id' => $this->admin->id,
            'admin_name' => $this->admin->name,
            'module' => 'users',
            'action' => 'user_block',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0',
            'affected_table' => 'users',
            'affected_record_id' => 2,
            'old_values' => ['status' => 'active'],
            'new_values' => ['status' => 'suspended'],
        ]);
    }

    /**
     * Test list audit logs.
     */
    public function test_admin_can_list_audit_logs_with_filters()
    {
        Sanctum::actingAs($this->admin, ['role:admin']);

        $response = $this->getJson('/api/v1/admin/audit-logs?module=users&search=Alice');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'audit_logs')
            ->assertJsonPath('audit_logs.0.action', 'user_block');
    }

    /**
     * Test get audit log details.
     */
    public function test_admin_can_get_audit_log_details()
    {
        Sanctum::actingAs($this->admin, ['role:admin']);

        $response = $this->getJson("/api/v1/admin/audit-logs/{$this->auditLog->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('audit_log.id', $this->auditLog->id)
            ->assertJsonPath('audit_log.admin_name', 'Alice Admin');
    }
}
