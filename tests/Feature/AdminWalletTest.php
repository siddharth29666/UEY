<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminWalletTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $rider;
    protected Wallet $wallet;

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

        $this->wallet = Wallet::create([
            'user_id' => $this->rider->id,
            'balance' => 50.00,
        ]);
    }

    /**
     * Test list wallets.
     */
    public function test_admin_can_list_wallets()
    {
        Sanctum::actingAs($this->admin, ['role:admin']);

        $response = $this->getJson('/api/v1/admin/wallets?search=John');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'wallets');
    }

    /**
     * Test manual credit.
     */
    public function test_admin_can_credit_wallet_manually()
    {
        Sanctum::actingAs($this->admin, ['role:admin']);

        $response = $this->postJson("/api/v1/admin/wallets/{$this->wallet->id}/credit", [
            'amount' => 20.00,
            'reason' => 'Referral reward credit adjustment',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('balance', 70);

        $this->assertEquals(70.00, $this->wallet->refresh()->balance);

        // Assert audit log exists
        $this->assertDatabaseHas('audit_logs', [
            'admin_id' => $this->admin->id,
            'action' => 'wallet_credit',
            'module' => 'wallets',
        ]);
    }

    /**
     * Test manual debit.
     */
    public function test_admin_can_debit_wallet_manually()
    {
        Sanctum::actingAs($this->admin, ['role:admin']);

        $response = $this->postJson("/api/v1/admin/wallets/{$this->wallet->id}/debit", [
            'amount' => 10.00,
            'reason' => 'Admin debit adjustment',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('balance', 40);

        $this->assertEquals(40.00, $this->wallet->refresh()->balance);

        // Assert audit log exists
        $this->assertDatabaseHas('audit_logs', [
            'admin_id' => $this->admin->id,
            'action' => 'wallet_debit',
            'module' => 'wallets',
        ]);
    }

    /**
     * Test manual debit validation (insufficient funds).
     */
    public function test_debit_fails_on_insufficient_funds()
    {
        Sanctum::actingAs($this->admin, ['role:admin']);

        $response = $this->postJson("/api/v1/admin/wallets/{$this->wallet->id}/debit", [
            'amount' => 100.00,
            'reason' => 'Overdraft debit',
        ]);

        $response->assertStatus(422);
        $this->assertEquals(50.00, $this->wallet->refresh()->balance);
    }

    /**
     * Test view wallet transactions ledger logs.
     */
    public function test_admin_can_view_wallet_transactions_history()
    {
        Sanctum::actingAs($this->admin, ['role:admin']);

        // Create transaction logs
        $this->postJson("/api/v1/admin/wallets/{$this->wallet->id}/credit", [
            'amount' => 20.00,
            'reason' => 'Referral credit',
        ]);

        $response = $this->getJson('/api/v1/admin/wallet-transactions?type=admin_credit');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'transactions', 'meta']);
    }
}
