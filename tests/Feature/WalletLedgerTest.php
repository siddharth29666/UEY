<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\WalletTransactionType;
use App\Models\Ledger;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\LedgerService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WalletLedgerTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================================
    // Helpers
    // =========================================================================

    private int $phoneCounter = 1000;

    private function makeRider(): User
    {
        $phone = '+447900'.(string) $this->phoneCounter++;

        $user = User::create([
            'name'     => 'Test Rider',
            'phone'    => $phone,
            'password' => Hash::make('password123'),
            'role'     => UserRole::RIDER,
            'status'   => UserStatus::ACTIVE,
        ]);

        Wallet::create([
            'user_id'  => $user->id,
            'balance'  => 0.00,
            'currency' => 'GBP',
            'status'   => 'active',
        ]);

        return $user;
    }

    private function makeAdmin(): User
    {
        $phone = '+447800'.(string) $this->phoneCounter++;

        return User::create([
            'name'     => 'Test Admin',
            'phone'    => $phone,
            'password' => Hash::make('password123'),
            'role'     => UserRole::ADMIN,
            'status'   => UserStatus::ACTIVE,
        ]);
    }

    private function getWallet(User $user): Wallet
    {
        return Wallet::where('user_id', $user->id)->firstOrFail();
    }

    // =========================================================================
    // 1. Credit creates a ledger entry
    // =========================================================================

    public function test_wallet_credit_creates_ledger_entry(): void
    {
        $rider  = $this->makeRider();
        $wallet = $this->getWallet($rider);

        $tx = app(WalletService::class)->credit(
            $wallet,
            50.00,
            WalletTransactionType::TOP_UP,
            'REF001',
            'Test topup'
        );

        $this->assertDatabaseHas('ledgers', [
            'wallet_transaction_id' => $tx->id,
            'wallet_id'             => $wallet->id,
            'user_id'               => $rider->id,
            'direction'             => 'credit',
        ]);

        $this->assertEquals(1, Ledger::where('wallet_transaction_id', $tx->id)->count());
    }

    // =========================================================================
    // 2. Debit creates a ledger entry
    // =========================================================================

    public function test_wallet_debit_creates_ledger_entry(): void
    {
        $rider  = $this->makeRider();
        $wallet = $this->getWallet($rider);
        $svc    = app(WalletService::class);

        $svc->credit($wallet, 100.00, WalletTransactionType::TOP_UP);
        $wallet->refresh();

        $tx = $svc->debit($wallet, 30.00, WalletTransactionType::RIDE_PAYMENT, 'RIDE001');

        $this->assertDatabaseHas('ledgers', [
            'wallet_transaction_id' => $tx->id,
            'direction'             => 'debit',
        ]);
    }

    // =========================================================================
    // 3. Duplicate ledger rows are impossible (createIfMissing is idempotent)
    // =========================================================================

    public function test_duplicate_ledger_entry_cannot_be_created(): void
    {
        $rider  = $this->makeRider();
        $wallet = $this->getWallet($rider);

        $tx = app(WalletService::class)->credit($wallet, 25.00, WalletTransactionType::REFERRAL_BONUS);

        // createIfMissing on already-existing entry should return null
        $result = app(LedgerService::class)->createIfMissing($tx);

        $this->assertNull($result);
        $this->assertEquals(1, Ledger::where('wallet_transaction_id', $tx->id)->count());
    }

    // =========================================================================
    // 4. wallet_transaction_id unique constraint enforced at DB level
    // =========================================================================

    public function test_wallet_transaction_id_uniqueness_at_database_level(): void
    {
        $rider  = $this->makeRider();
        $wallet = $this->getWallet($rider);

        $tx = app(WalletService::class)->credit($wallet, 10.00, WalletTransactionType::ADMIN_CREDIT);

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);

        Ledger::create([
            'wallet_transaction_id' => $tx->id,
            'wallet_id'             => $wallet->id,
            'user_id'               => $rider->id,
            'reference'             => null,
            'transaction_type'      => 'admin_credit',
            'direction'             => 'credit',
            'amount'                => 10.00,
            'currency'              => 'GBP',
            'source'                => 'admin_credit',
        ]);
    }

    // =========================================================================
    // 5. Ledger is immutable — update throws RuntimeException
    // =========================================================================

    public function test_ledger_entry_cannot_be_updated(): void
    {
        $rider  = $this->makeRider();
        $wallet = $this->getWallet($rider);

        app(WalletService::class)->credit($wallet, 50.00, WalletTransactionType::TOP_UP);

        $ledger = Ledger::first();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('immutable');

        $ledger->update(['amount' => 9999.00]);
    }

    // =========================================================================
    // 6. Ledger is immutable — delete throws RuntimeException
    // =========================================================================

    public function test_ledger_entry_cannot_be_deleted(): void
    {
        $rider  = $this->makeRider();
        $wallet = $this->getWallet($rider);

        app(WalletService::class)->credit($wallet, 50.00, WalletTransactionType::TOP_UP);

        $ledger = Ledger::first();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('immutable');

        $ledger->delete();
    }

    // =========================================================================
    // 7. Settlement command auto-creates missing ledger rows
    // =========================================================================

    public function test_settlement_command_creates_missing_ledger_rows(): void
    {
        $rider  = $this->makeRider();
        $wallet = $this->getWallet($rider);

        // Manually insert a WalletTransaction without a ledger (pre-ledger history)
        $tx = WalletTransaction::create([
            'wallet_id'        => $wallet->id,
            'type'             => 'credit',
            'transaction_type' => WalletTransactionType::ADMIN_CREDIT,
            'amount'           => 75.00,
            'balance_before'   => 0.00,
            'balance_after'    => 75.00,
            'status'           => 'completed',
            'reference'        => 'MANUAL001',
        ]);

        $this->assertEquals(0, Ledger::where('wallet_transaction_id', $tx->id)->count());

        $this->artisan('app:wallet-settlement')->assertSuccessful();

        $this->assertEquals(1, Ledger::where('wallet_transaction_id', $tx->id)->count());
    }

    // =========================================================================
    // 8. Backfill command is idempotent
    // =========================================================================

    public function test_ledger_backfill_command_is_idempotent(): void
    {
        $rider  = $this->makeRider();
        $wallet = $this->getWallet($rider);

        foreach ([20.00, 40.00] as $amount) {
            WalletTransaction::create([
                'wallet_id'        => $wallet->id,
                'type'             => 'credit',
                'transaction_type' => WalletTransactionType::TOP_UP,
                'amount'           => $amount,
                'balance_before'   => 0.00,
                'balance_after'    => $amount,
                'status'           => 'completed',
            ]);
        }

        $this->assertEquals(0, Ledger::count());

        $this->artisan('app:ledger-backfill')->assertSuccessful();
        $this->assertEquals(2, Ledger::count());

        // Second run must not create duplicates
        $this->artisan('app:ledger-backfill')->assertSuccessful();
        $this->assertEquals(2, Ledger::count());
    }

    // =========================================================================
    // 9. wallet_transactions remains source of truth (ledger has no balances)
    // =========================================================================

    public function test_wallet_transaction_remains_source_of_truth(): void
    {
        $rider  = $this->makeRider();
        $wallet = $this->getWallet($rider);
        $svc    = app(WalletService::class);

        $svc->credit($wallet, 100.00, WalletTransactionType::TOP_UP);
        $wallet->refresh();
        $svc->debit($wallet, 40.00, WalletTransactionType::RIDE_PAYMENT);
        $wallet->refresh();

        $this->assertEquals('60.00', $wallet->balance);

        // Ledger stores no balance columns
        $ledger = Ledger::latest()->first();
        $this->assertArrayNotHasKey('balance_before', $ledger->toArray());
        $this->assertArrayNotHasKey('balance_after', $ledger->toArray());
    }

    // =========================================================================
    // 10. Admin can list ledger entries
    // =========================================================================

    public function test_admin_can_list_ledger_entries(): void
    {
        $admin  = $this->makeAdmin();
        $rider  = $this->makeRider();
        $wallet = $this->getWallet($rider);

        app(WalletService::class)->credit($wallet, 50.00, WalletTransactionType::TOP_UP);

        Sanctum::actingAs($admin, ['role:admin']);
        $response = $this->getJson('/api/v1/admin/ledgers');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [['id', 'wallet_transaction_id', 'direction', 'amount', 'source']],
                'meta' => ['total', 'current_page'],
            ]);
    }

    // =========================================================================
    // 11. Admin can filter ledger entries by direction
    // =========================================================================

    public function test_admin_can_filter_ledger_by_direction(): void
    {
        $admin  = $this->makeAdmin();
        $rider  = $this->makeRider();
        $wallet = $this->getWallet($rider);
        $svc    = app(WalletService::class);

        $svc->credit($wallet, 50.00, WalletTransactionType::TOP_UP);
        $wallet->refresh();
        $svc->debit($wallet, 10.00, WalletTransactionType::RIDE_PAYMENT);

        Sanctum::actingAs($admin, ['role:admin']);
        $response = $this->getJson('/api/v1/admin/ledgers?direction=debit');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('debit', $response->json('data.0.direction'));
    }

    // =========================================================================
    // 12. Admin can view a single ledger entry
    // =========================================================================

    public function test_admin_can_view_single_ledger_entry(): void
    {
        $admin  = $this->makeAdmin();
        $rider  = $this->makeRider();
        $wallet = $this->getWallet($rider);

        app(WalletService::class)->credit($wallet, 50.00, WalletTransactionType::TOP_UP, 'REF_SHOW');

        $ledger = Ledger::first();

        Sanctum::actingAs($admin, ['role:admin']);
        $this->getJson("/api/v1/admin/ledgers/{$ledger->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $ledger->id)
            ->assertJsonPath('data.reference', 'REF_SHOW');
    }

    // =========================================================================
    // 13. Rider cannot access admin ledger endpoint
    // =========================================================================

    public function test_rider_cannot_access_admin_ledger_endpoint(): void
    {
        $rider  = $this->makeRider();
        $wallet = $this->getWallet($rider);

        app(WalletService::class)->credit($wallet, 50.00, WalletTransactionType::TOP_UP);

        Sanctum::actingAs($rider, ['role:rider']);
        $this->getJson('/api/v1/admin/ledgers')
            ->assertForbidden();
    }

    // =========================================================================
    // 14. Rider can view their own ledger history
    // =========================================================================

    public function test_rider_can_view_own_ledger_history(): void
    {
        $rider  = $this->makeRider();
        $wallet = $this->getWallet($rider);

        app(WalletService::class)->credit($wallet, 50.00, WalletTransactionType::TOP_UP);

        Sanctum::actingAs($rider, ['role:rider']);
        $response = $this->getJson('/api/v1/wallet/ledger');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [['id', 'direction', 'amount', 'source']],
                'meta',
            ]);

        $this->assertCount(1, $response->json('data'));
    }

    // =========================================================================
    // 15. Rider ledger is scoped to own user
    // =========================================================================

    public function test_rider_ledger_is_scoped_to_own_user(): void
    {
        $rider1  = $this->makeRider();
        $wallet1 = $this->getWallet($rider1);
        $rider2  = $this->makeRider();
        $wallet2 = $this->getWallet($rider2);
        $svc     = app(WalletService::class);

        $svc->credit($wallet1, 100.00, WalletTransactionType::TOP_UP);
        $svc->credit($wallet2, 200.00, WalletTransactionType::TOP_UP);

        Sanctum::actingAs($rider1, ['role:rider']);
        $response = $this->getJson('/api/v1/wallet/ledger');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($rider1->id, $response->json('data.0.user_id'));
    }

    // =========================================================================
    // 16. LedgerService::findByTransaction returns correct entry
    // =========================================================================

    public function test_find_by_transaction_returns_correct_ledger(): void
    {
        $rider  = $this->makeRider();
        $wallet = $this->getWallet($rider);

        $tx    = app(WalletService::class)->credit($wallet, 75.00, WalletTransactionType::REFERRAL_BONUS, 'REF_FIND');
        $found = app(LedgerService::class)->findByTransaction($tx->id);

        $this->assertNotNull($found);
        $this->assertEquals($tx->id, $found->wallet_transaction_id);
    }

    // =========================================================================
    // 17. WalletTransaction has ledger relationship
    // =========================================================================

    public function test_wallet_transaction_has_ledger_relationship(): void
    {
        $rider  = $this->makeRider();
        $wallet = $this->getWallet($rider);

        $tx     = app(WalletService::class)->credit($wallet, 50.00, WalletTransactionType::TOP_UP);
        $ledger = $tx->ledger;

        $this->assertNotNull($ledger);
        $this->assertInstanceOf(Ledger::class, $ledger);
        $this->assertEquals($tx->id, $ledger->wallet_transaction_id);
    }
}
