<?php

namespace App\Listeners;

use App\Events\WalletUpdated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class BroadcastWallet implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'realtime';

    public int $tries = 3;

    public int $timeout = 120;

    /**
     * Handle the event.
     */
    public function handle($event): void
    {
        $wallet = $event->user ? $event->user->wallet : null;
        if (! $wallet) {
            return;
        }

        $amount = (float) ($event->data['amount'] ?? 0.00);
        $type = $event->data['type'] ?? 'credit';
        $reason = $event->data['reason'] ?? 'Wallet updated';

        event(new WalletUpdated($wallet, $amount, $type, $reason));

        Log::info("BroadcastWallet: WalletUpdated broadcast event fired for wallet {$wallet->id}.");
    }
}
