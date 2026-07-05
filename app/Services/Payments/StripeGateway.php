<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Models\Ride;
use App\Models\WalletTransaction;

class StripeGateway implements PaymentGatewayInterface
{
    public function process(Payment $payment, Ride $ride): string
    {
        // Mock Stripe API charge success
        // Credit driver's wallet with earnings (collected by platform via Stripe)
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
                    'description' => "Stripe payment earnings credited for Ride #{$ride->id}",
                ]);
            }
        }

        // Generate unique transaction reference
        return 'PAY-' . now()->format('Ymd') . '-' . str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT);
    }
}
