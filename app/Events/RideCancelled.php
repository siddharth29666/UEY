<?php

namespace App\Events;

use App\Models\Ride;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RideCancelled implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $broadcastQueue = 'realtime';

    public function __construct(
        public Ride $ride
    ) {}

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        $channels = [
            new PresenceChannel('admins'),
        ];

        // Broadcast to rider
        if ($this->ride->rider_id) {
            $channels[] = new PrivateChannel('rider.'.$this->ride->rider_id);
        }

        // Broadcast to driver if assigned
        if ($this->ride->driverProfile && $this->ride->driverProfile->user_id) {
            $channels[] = new PrivateChannel('driver.'.$this->ride->driverProfile->user_id);
        }

        return $channels;
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'ride_id' => $this->ride->id,
            'status' => $this->ride->status->value,
            'cancelled_by' => $this->ride->cancelled_by,
            'cancel_reason' => $this->ride->cancel_reason,
            'cancelled_at' => $this->ride->cancelled_at ? $this->ride->cancelled_at->toIso8601String() : now()->toIso8601String(),
        ];
    }
}
