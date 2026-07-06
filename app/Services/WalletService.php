<?php

namespace App\Services;

use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use App\Enums\WithdrawalStatus;
use App\Exceptions\InsufficientWalletBalanceException;
use App\Models\ProcessedStripeEvent;
use App\Models\Wallet;
use App\Models\WalletTopup;
use App\Models\WalletTransaction;
use App\Models\WithdrawalRequest;
use Illuminate\Support\Facades\DB;

class WalletService
{
    public function __construct(
        protected StripeService $stripeService
    ) {}

    /**
     * Credit funds to a user wallet.
     */
    public function credit(
        Wallet $wallet,
        float $amount,
        WalletTransactionType $type,
        ?string $reference = null,
        ?string $remarks = null,
        array $metadata = []
    ): WalletTransaction {
        return DB::transaction(function () use ($wallet, $amount, $type, $reference, $remarks, $metadata) {
            $wallet->refresh();
            $before = (float) $wallet->balance;
            $after = $before + $amount;

            $wallet->update(['balance' => $after]);

            return WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'type' => 'credit',
                'transaction_type' => $type,
                'amount' => $amount,
                'balance_before' => $before,
                'balance_after' => $after,
                'status' => WalletTransactionStatus::COMPLETED,
                'reference' => $reference,
                'remarks' => $remarks,
                'metadata' => $metadata,
            ]);
        });
    }

    /**
     * Debit funds from a user wallet.
     */
    public function debit(
        Wallet $wallet,
        float $amount,
        WalletTransactionType $type,
        ?string $reference = null,
        ?string $remarks = null,
        array $metadata = []
    ): WalletTransaction {
        return DB::transaction(function () use ($wallet, $amount, $type, $reference, $remarks, $metadata) {
            $wallet->refresh();
            $before = (float) $wallet->balance;

            // Enforce that riders cannot go below 0
            if ($wallet->user->role->value === 'rider' && $before < $amount) {
                $shortfall = $amount - $before;
                throw new InsufficientWalletBalanceException($before, $amount, $shortfall);
            }

            // Enforce that general users cannot withdraw beyond balance (except drivers who can go negative on commission debits)
            if ($type === WalletTransactionType::WITHDRAWAL && $before < $amount) {
                throw new \Exception("Withdrawal amount exceeds wallet balance.");
            }

            $after = $before - $amount;
            $wallet->update(['balance' => $after]);

            return WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'type' => 'debit',
                'transaction_type' => $type,
                'amount' => $amount,
                'balance_before' => $before,
                'balance_after' => $after,
                'status' => WalletTransactionStatus::COMPLETED,
                'reference' => $reference,
                'remarks' => $remarks,
                'metadata' => $metadata,
            ]);
        });
    }

    /**
     * Request a Stripe PaymentIntent for a wallet top-up.
     */
    public function createTopup(Wallet $wallet, float $amount): WalletTopup
    {
        if ($amount < 5.00 || $amount > 5000.00) {
            throw new \Exception("Top-up amount must be between 5.00 and 5000.00.");
        }

        return DB::transaction(function () use ($wallet, $amount) {
            $intent = $this->stripeService->createPaymentIntent($amount, $wallet->currency, [
                'wallet_id' => $wallet->id,
            ]);

            return WalletTopup::create([
                'wallet_id' => $wallet->id,
                'amount' => $amount,
                'stripe_payment_intent' => $intent->id,
                'payment_status' => 'pending',
            ]);
        });
    }

    /**
     * Process Stripe webhook payload safely (idempotent verification).
     */
    public function processWebhookEvent(array $payload): void
    {
        $eventId = $payload['id'] ?? null;
        if (!$eventId) {
            return;
        }

        // Idempotency: Ignore duplicate event processing
        $alreadyProcessed = ProcessedStripeEvent::where('event_id', $eventId)->exists();
        if ($alreadyProcessed) {
            return;
        }

        DB::transaction(function () use ($payload, $eventId) {
            ProcessedStripeEvent::create(['event_id' => $eventId]);

            $type = $payload['type'] ?? '';
            $dataObject = $payload['data']['object'] ?? [];
            $intentId = $dataObject['id'] ?? null;

            if (!$intentId) {
                return;
            }

            $topup = WalletTopup::where('stripe_payment_intent', $intentId)->first();
            if (!$topup || $topup->payment_status !== 'pending') {
                return;
            }

            if ($type === 'payment_intent.succeeded') {
                $topup->update([
                    'payment_status' => 'completed',
                    'gateway_response' => $payload,
                    'paid_at' => now(),
                ]);

                // Credit the wallet
                $this->credit(
                    $topup->wallet,
                    (float) $topup->amount,
                    WalletTransactionType::TOP_UP,
                    'topup_' . $topup->id,
                    'Stripe wallet top-up completed',
                    ['stripe_payment_intent' => $intentId]
                );
            } elseif ($type === 'payment_intent.payment_failed') {
                $topup->update([
                    'payment_status' => 'failed',
                    'gateway_response' => $payload,
                ]);
            }
        });
    }

    /**
     * Request a withdrawal.
     */
    public function requestWithdrawal(Wallet $wallet, float $amount, ?int $bankAccountId = null): WithdrawalRequest
    {
        if ($amount <= 0) {
            throw new \Exception("Cannot withdraw zero or negative amount.");
        }

        if ($wallet->balance < $amount) {
            throw new \Exception("Withdrawal amount exceeds wallet balance.");
        }

        return WithdrawalRequest::create([
            'wallet_id' => $wallet->id,
            'amount' => $amount,
            'status' => WithdrawalStatus::PENDING,
            'bank_account_id' => $bankAccountId,
        ]);
    }

    /**
     * Approve and complete a withdrawal request.
     */
    public function approveWithdrawal(WithdrawalRequest $request, ?string $adminNote = null): void
    {
        if ($request->status !== WithdrawalStatus::PENDING) {
            throw new \Exception("Withdrawal request is not pending.");
        }

        DB::transaction(function () use ($request, $adminNote) {
            $wallet = $request->wallet;

            // Debit the wallet ledger
            $this->debit(
                $wallet,
                (float) $request->amount,
                WalletTransactionType::WITHDRAWAL,
                'withdrawal_' . $request->id,
                'Approved withdrawal request #' . $request->id,
                ['bank_account_id' => $request->bank_account_id]
            );

            $request->update([
                'status' => WithdrawalStatus::COMPLETED,
                'admin_note' => $adminNote,
                'processed_at' => now(),
            ]);
        });
    }

    /**
     * Reject a withdrawal request.
     */
    public function rejectWithdrawal(WithdrawalRequest $request, ?string $adminNote = null): void
    {
        if ($request->status !== WithdrawalStatus::PENDING) {
            throw new \Exception("Withdrawal request is not pending.");
        }

        $request->update([
            'status' => WithdrawalStatus::REJECTED,
            'admin_note' => $adminNote,
            'processed_at' => now(),
        ]);
    }
}
