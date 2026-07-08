<?php

namespace App\Services;

use App\Enums\DocumentStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserStatus;
use App\Enums\WalletTransactionType;
use App\Models\Payment;
use App\Models\Ride;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;

class AdminService
{
    public function __construct(
        protected AuditLogService $auditService,
        protected WalletService $walletService
    ) {}

    /**
     * Suspend/Block a rider or driver user account.
     */
    public function blockUser(User $user, User $admin): void
    {
        if ($user->status === UserStatus::SUSPENDED) {
            return;
        }

        DB::transaction(function () use ($user, $admin) {
            $oldValues = $user->toArray();
            $user->update(['status' => UserStatus::SUSPENDED]);

            $this->auditService->log(
                $admin,
                'users',
                'user_block',
                'users',
                $user->id,
                $oldValues,
                $user->fresh()->toArray()
            );
        });
    }

    /**
     * Unsuspend/Unblock a rider or driver user account.
     */
    public function unblockUser(User $user, User $admin): void
    {
        if ($user->status === UserStatus::ACTIVE) {
            return;
        }

        DB::transaction(function () use ($user, $admin) {
            $oldValues = $user->toArray();
            $user->update(['status' => UserStatus::ACTIVE]);

            $this->auditService->log(
                $admin,
                'users',
                'user_unblock',
                'users',
                $user->id,
                $oldValues,
                $user->fresh()->toArray()
            );
        });
    }

    /**
     * Manually approve a driver user status.
     */
    public function approveDriver(User $driverUser, User $admin): void
    {
        $profile = $driverUser->driverProfile;
        if (!$profile) {
            throw new \Exception("User does not have a driver profile.");
        }

        DB::transaction(function () use ($driverUser, $profile, $admin) {
            $oldValues = $driverUser->toArray();
            $driverUser->update(['status' => UserStatus::ACTIVE]);

            // Automatically approve all their pending documents
            $profile->documents()->where('status', DocumentStatus::PENDING)->update([
                'status' => DocumentStatus::APPROVED,
            ]);

            $this->auditService->log(
                $admin,
                'drivers',
                'driver_approve',
                'users',
                $driverUser->id,
                $oldValues,
                $driverUser->fresh()->toArray()
            );
        });
    }

    /**
     * Manually reject a driver.
     */
    public function rejectDriver(User $driverUser, ?string $reason, User $admin): void
    {
        $profile = $driverUser->driverProfile;
        if (!$profile) {
            throw new \Exception("User does not have a driver profile.");
        }

        DB::transaction(function () use ($driverUser, $profile, $reason, $admin) {
            $oldValues = $driverUser->toArray();
            $driverUser->update(['status' => UserStatus::PENDING_APPROVAL]); // reset to pending onboarding

            // Reject pending documents
            $profile->documents()->where('status', DocumentStatus::PENDING)->update([
                'status' => DocumentStatus::REJECTED,
                'rejection_reason' => $reason ?: 'Rejected by administrator.',
            ]);

            $this->auditService->log(
                $admin,
                'drivers',
                'driver_reject',
                'users',
                $driverUser->id,
                $oldValues,
                $driverUser->fresh()->toArray()
            );
        });
    }

    /**
     * Credit funds to a wallet with full audit trail logging.
     */
    public function creditWallet(Wallet $wallet, float $amount, string $reason, User $admin): void
    {
        DB::transaction(function () use ($wallet, $amount, $reason, $admin) {
            $oldValues = ['balance' => (float) $wallet->balance];

            $this->walletService->credit(
                $wallet,
                $amount,
                WalletTransactionType::ADMIN_CREDIT,
                'admin_credit_' . uniqid(),
                $reason
            );

            $this->auditService->log(
                $admin,
                'wallets',
                'wallet_credit',
                'wallets',
                $wallet->id,
                $oldValues,
                ['balance' => (float) $wallet->fresh()->balance]
            );
        });
    }

    /**
     * Debit funds from a wallet with full audit trail logging.
     */
    public function debitWallet(Wallet $wallet, float $amount, string $reason, User $admin): void
    {
        DB::transaction(function () use ($wallet, $amount, $reason, $admin) {
            $oldValues = ['balance' => (float) $wallet->balance];

            $this->walletService->debit(
                $wallet,
                $amount,
                WalletTransactionType::ADMIN_DEBIT,
                'admin_debit_' . uniqid(),
                $reason
            );

            $this->auditService->log(
                $admin,
                'wallets',
                'wallet_debit',
                'wallets',
                $wallet->id,
                $oldValues,
                ['balance' => (float) $wallet->fresh()->balance]
            );
        });
    }

    /**
     * Reverse a ride payment and credit the rider wallet, debited from driver if applicable.
     */
    public function refundRide(Ride $ride, User $admin): Payment
    {
        $payment = $ride->payment;
        if (!$payment) {
            throw new \Exception("No payment found to refund.");
        }
        if ($payment->payment_status === PaymentStatus::REFUNDED) {
            throw new \Exception("Payment is already refunded.");
        }

        return DB::transaction(function () use ($ride, $payment, $admin) {
            // 1. Credit Rider
            $riderWallet = $ride->rider->wallet()->firstOrCreate(
                ['user_id' => $ride->rider_id],
                ['balance' => 0.00]
            );
            $this->walletService->credit(
                $riderWallet,
                (float) $payment->total,
                WalletTransactionType::REFUND,
                'refund_' . $payment->id,
                'Refund for ride #' . $ride->id
            );

            // 2. Debit Driver
            if ($payment->driver_earning > 0 && $ride->driverProfile) {
                $driverUser = $ride->driverProfile->user;
                $driverWallet = $driverUser->wallet()->firstOrCreate(
                    ['user_id' => $driverUser->id],
                    ['balance' => 0.00]
                );
                $this->walletService->debit(
                    $driverWallet,
                    (float) $payment->driver_earning,
                    WalletTransactionType::REFUND,
                    'refund_debit_' . $payment->id,
                    'Reverse earnings for ride #' . $ride->id
                );
            }

            // 3. Update Statuses
            $oldValues = $payment->toArray();
            $payment->update(['payment_status' => PaymentStatus::REFUNDED]);
            $ride->update(['payment_status' => 'refunded']);

            $this->auditService->log(
                $admin,
                'rides',
                'ride_refund',
                'payments',
                $payment->id,
                $oldValues,
                $payment->fresh()->toArray()
            );

            return $payment;
        });
    }
}
