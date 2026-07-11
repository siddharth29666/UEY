<?php

namespace App\Services;

use App\Enums\LedgerSource;
use App\Enums\WalletTransactionType;
use App\Models\Ledger;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LedgerService
{
    /**
     * Map a WalletTransactionType to its corresponding LedgerSource.
     */
    protected function resolveSource(WalletTransaction $tx): LedgerSource
    {
        // If metadata provides an explicit source override (e.g. stripe vs cash), use it.
        $metadata = is_array($tx->metadata) ? $tx->metadata : [];
        if (! empty($metadata['ledger_source'])) {
            $raw = $metadata['ledger_source'];
            if ($case = LedgerSource::tryFrom($raw)) {
                return $case;
            }
        }

        return match ($tx->transaction_type) {
            WalletTransactionType::TOP_UP => LedgerSource::WALLET_TOPUP,
            WalletTransactionType::RIDE_PAYMENT => LedgerSource::RIDE_PAYMENT,
            WalletTransactionType::RIDE_EARNING => LedgerSource::RIDE_PAYMENT,
            WalletTransactionType::WITHDRAWAL => LedgerSource::WITHDRAWAL,
            WalletTransactionType::REFUND => LedgerSource::REFUND,
            WalletTransactionType::REFERRAL_BONUS => LedgerSource::REFERRAL_BONUS,
            WalletTransactionType::ADMIN_CREDIT => LedgerSource::ADMIN_CREDIT,
            WalletTransactionType::ADMIN_DEBIT => LedgerSource::ADMIN_DEBIT,
            default => LedgerSource::MANUAL_ADJUSTMENT,
        };
    }

    /**
     * Create an immutable ledger entry from a wallet transaction.
     *
     * This is the primary write path — called immediately after a WalletTransaction is persisted.
     */
    public function createFromWalletTransaction(WalletTransaction $tx): Ledger
    {
        // Safety guard: never create a duplicate entry.
        if ($existing = $this->findByTransaction($tx->id)) {
            return $existing;
        }

        $wallet = $tx->wallet;
        $user = $wallet->user;

        return DB::transaction(function () use ($tx, $wallet, $user) {
            return Ledger::create([
                'wallet_transaction_id' => $tx->id,
                'wallet_id' => $wallet->id,
                'user_id' => $user->id,
                'reference' => $tx->reference,
                'transaction_type' => $tx->transaction_type instanceof \BackedEnum
                    ? $tx->transaction_type->value
                    : (string) $tx->transaction_type,
                'direction' => $tx->type, // 'credit' or 'debit'
                'amount' => (float) $tx->amount,
                'currency' => $wallet->currency ?? 'GBP',
                'source' => $this->resolveSource($tx),
                'remarks' => $tx->remarks,
                'metadata' => $tx->metadata ?? [],
            ]);
        });
    }

    /**
     * Create a ledger entry only if one does not already exist for the transaction.
     * Safe to call multiple times (idempotent).
     */
    public function createIfMissing(WalletTransaction $tx): ?Ledger
    {
        if ($this->findByTransaction($tx->id)) {
            return null; // Already exists — skip.
        }

        try {
            return $this->createFromWalletTransaction($tx);
        } catch (\Throwable $e) {
            Log::warning("LedgerService::createIfMissing failed for tx #{$tx->id}: {$e->getMessage()}");

            return null;
        }
    }

    /**
     * Find the ledger entry for a given wallet_transaction_id.
     */
    public function findByTransaction(int $transactionId): ?Ledger
    {
        return Ledger::where('wallet_transaction_id', $transactionId)->first();
    }
}
