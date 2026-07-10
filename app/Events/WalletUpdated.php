<?php

namespace App\Events;

use App\Models\Wallet;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WalletUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $broadcastQueue = 'realtime';

    public function __construct(
        public Wallet $wallet,
        public float $amount = 0.00,
        public string $type = 'credit',
        public string $reason = ''
    ) {}

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('wallet.'.$this->wallet->id),
            new PresenceChannel('admins'),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'wallet_id' => $this->wallet->id,
            'user_id' => $this->wallet->user_id,
            'balance' => (float) $this->wallet->balance,
            'amount' => (float) $this->amount,
            'type' => $this->type,
            'reason' => $this->reason,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
