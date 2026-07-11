<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SchedulerWalletSettlementTest extends TestCase
{
    use RefreshDatabase;

    protected User $rider;

    protected Wallet $wallet;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rider = User::create([
            'name' => 'Alice Rider',
            'phone' => '+447911111111',
            'email' => 'alice@example.com',
            'password' => bcrypt('password'),
            'role' => UserRole::RIDER,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->wallet = Wallet::create([
            'user_id' => $this->rider->id,
            'balance' => 150.00,
            'currency' => 'USD',
            'status' => 'active',
        ]);

        // Create transaction
        WalletTransaction::create([
            'wallet_id' => $this->wallet->id,
            'type' => 'credit',
            'transaction_type' => WalletTransactionType::TOP_UP,
            'amount' => 50.00,
            'balance_before' => 100.00,
            'balance_after' => 150.00,
            'status' => WalletTransactionStatus::COMPLETED,
        ]);
    }

    public function test_wallet_settlement_command()
    {
        // Run command
        Artisan::call('app:wallet-settlement');

        // Assert scheduler log exists
        $this->assertDatabaseHas('scheduler_logs', [
            'command' => 'app:wallet-settlement',
            'status' => 'success',
        ]);
    }
}
