<?php

namespace App\Events;

use App\Models\Ride;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RideRequested implements ShouldBroadcast
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
        return [
            new PresenceChannel('admins'),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'ride_id' => $this->ride->id,
            'rider_id' => $this->ride->rider_id,
            'pickup_address' => $this->ride->pickup_address,
            'destination_address' => $this->ride->destination_address,
            'estimated_fare' => (float) $this->ride->estimated_fare,
            'status' => $this->ride->status->value,
            'created_at' => $this->ride->created_at->toIso8601String(),
        ];
    }
}
