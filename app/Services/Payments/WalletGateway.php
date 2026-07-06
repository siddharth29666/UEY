<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Models\Ride;
use App\Models\WalletTransaction;
use App\Exceptions\InsufficientWalletBalanceException;

class WalletGateway implements PaymentGatewayInterface
{
    public function process(Payment $payment, Ride $ride): string
    {
        $rider = $ride->rider;
        $riderWallet = $rider->wallet()->firstOrCreate(
            ['user_id' => $rider->id],
            ['balance' => 0.00]
        );

        // Validate wallet balance
        if ($riderWallet->balance < $payment->total) {
            $shortfall = $payment->total - $riderWallet->balance;
            throw new InsufficientWalletBalanceException(
                (float) $riderWallet->balance,
                (float) $payment->total,
                (float) $shortfall
            );
        }

        $walletService = app(\App\Services\WalletService::class);

        // Debit rider's wallet
        $walletService->debit(
            $riderWallet,
            (float) $payment->total,
            \App\Enums\WalletTransactionType::RIDE_PAYMENT,
            'ride_' . $ride->id,
            "Payment debited for Ride #{$ride->id}"
        );

        // Credit driver's wallet with earnings
        $driverProfile = $ride->driverProfile;
        if ($driverProfile) {
            $driverUser = $driverProfile->user;
            if ($driverUser) {
                $driverWallet = $driverUser->wallet()->firstOrCreate(
                    ['user_id' => $driverUser->id],
                    ['balance' => 0.00]
                );
                $walletService->credit(
                    $driverWallet,
                    (float) $payment->driver_earning,
                    \App\Enums\WalletTransactionType::RIDE_EARNING,
                    'ride_' . $ride->id,
                    "Earnings credited for Ride #{$ride->id}"
                );
            }
        }

        // Generate unique transaction reference
        return 'PAY-' . now()->format('Ymd') . '-' . str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT);
    }
}
