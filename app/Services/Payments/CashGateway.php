<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Models\Ride;
use App\Models\WalletTransaction;

class CashGateway implements PaymentGatewayInterface
{
    public function process(Payment $payment, Ride $ride): string
    {
        // Platform commission is debited from driver's wallet (collected from cash fares)
        $driverProfile = $ride->driverProfile;
        if ($driverProfile) {
            $driverUser = $driverProfile->user;
            if ($driverUser) {
                // Find or create driver's wallet
                $wallet = $driverUser->wallet()->firstOrCreate(
                    ['user_id' => $driverUser->id],
                    ['balance' => 0.00]
                );

                $walletService = app(\App\Services\WalletService::class);
                $walletService->debit(
                    $wallet,
                    (float) $payment->platform_commission,
                    \App\Enums\WalletTransactionType::ADMIN_DEBIT,
                    'ride_' . $ride->id,
                    "Commission debited for cash Ride #{$ride->id}"
                );
            }
        }

        // Generate unique transaction reference
        return 'PAY-' . now()->format('Ymd') . '-' . str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT);
    }
}
