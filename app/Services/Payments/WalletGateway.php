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

        // Debit rider's wallet
        $riderWallet->decrement('balance', $payment->total);
        WalletTransaction::create([
            'wallet_id' => $riderWallet->id,
            'type' => 'debit',
            'amount' => $payment->total,
            'reference' => 'ride_' . $ride->id,
            'description' => "Payment debited for Ride #{$ride->id}",
        ]);

        // Credit driver's wallet with earnings
        $driverProfile = $ride->driverProfile;
        if ($driverProfile) {
            $driverUser = $driverProfile->user;
            if ($driverUser) {
                $driverWallet = $driverUser->wallet()->firstOrCreate(
                    ['user_id' => $driverUser->id],
                    ['balance' => 0.00]
                );
                $driverWallet->increment('balance', $payment->driver_earning);
                WalletTransaction::create([
                    'wallet_id' => $driverWallet->id,
                    'type' => 'credit',
                    'amount' => $payment->driver_earning,
                    'reference' => 'ride_' . $ride->id,
                    'description' => "Earnings credited for Ride #{$ride->id}",
                ]);
            }
        }

        // Generate unique transaction reference
        return 'PAY-' . now()->format('Ymd') . '-' . str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT);
    }
}
