<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\WithdrawalStatus;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WithdrawalRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WithdrawalApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $driver;

    protected Wallet $wallet;

    protected WithdrawalRequest $withdrawal;

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
            'status' => UserStatus::ACTIVE,
        ]);

        $this->wallet = Wallet::create([
            'user_id' => $this->driver->id,
            'balance' => 100.00,
        ]);

        $this->withdrawal = WithdrawalRequest::create([
            'wallet_id' => $this->wallet->id,
            'amount' => 40.00,
            'status' => WithdrawalStatus::PENDING,
            'bank_account_id' => 1,
        ]);
    }

    /**
     * Test list withdrawals.
     */
    public function test_admin_can_list_withdrawals()
    {
        Sanctum::actingAs($this->admin, ['role:admin']);

        $response = $this->getJson('/api/v1/admin/withdrawals?status=pending');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'withdrawals');
    }

    /**
     * Test approve withdrawal.
     */
    public function test_admin_can_approve_withdrawal()
    {
        Sanctum::actingAs($this->admin, ['role:admin']);

        $response = $this->postJson("/api/v1/admin/withdrawals/{$this->withdrawal->id}/approve", [
            'admin_note' => 'Payment processed successfully',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertEquals(WithdrawalStatus::COMPLETED, $this->withdrawal->refresh()->status);
        $this->assertEquals(60.00, $this->wallet->refresh()->balance);

        // Assert audit log exists
        $this->assertDatabaseHas('audit_logs', [
            'admin_id' => $this->admin->id,
            'action' => 'withdrawal_approve',
            'module' => 'withdrawals',
        ]);
    }

    /**
     * Test reject withdrawal.
     */
    public function test_admin_can_reject_withdrawal()
    {
        Sanctum::actingAs($this->admin, ['role:admin']);

        $response = $this->postJson("/api/v1/admin/withdrawals/{$this->withdrawal->id}/reject", [
            'admin_note' => 'Documents invalid',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertEquals(WithdrawalStatus::REJECTED, $this->withdrawal->refresh()->status);
        $this->assertEquals(100.00, $this->wallet->refresh()->balance); // Balance remains unchanged

        // Assert audit log exists
        $this->assertDatabaseHas('audit_logs', [
            'admin_id' => $this->admin->id,
            'action' => 'withdrawal_reject',
            'module' => 'withdrawals',
        ]);
    }
}
