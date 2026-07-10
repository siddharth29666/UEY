<?php

namespace App\Events;

use App\Models\Payment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentCompleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $broadcastQueue = 'realtime';

    public function __construct(
        public Payment $payment
    ) {}

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('rider.'.$this->payment->rider_id),
            new PresenceChannel('presence-admins'),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'payment_id' => $this->payment->id,
            'ride_id' => $this->payment->ride_id,
            'rider_id' => $this->payment->rider_id,
            'amount' => (float) $this->payment->total,
            'payment_method' => $this->payment->payment_method,
            'status' => $this->payment->payment_status,
            'transaction_reference' => $this->payment->transaction_reference,
            'paid_at' => $this->payment->paid_at ? $this->payment->paid_at->toIso8601String() : null,
        ];
    }
}
