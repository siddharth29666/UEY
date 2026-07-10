<?php

namespace App\Events;

use App\Models\Ride;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RideAccepted implements ShouldBroadcast
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
            new PrivateChannel('rider.'.$this->ride->rider_id),
            new PresenceChannel('admins'),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        $driver = $this->ride->driverProfile ? $this->ride->driverProfile->user : null;

        return [
            'ride_id' => $this->ride->id,
            'status' => $this->ride->status->value,
            'driver' => $driver ? [
                'id' => $driver->id,
                'name' => $driver->name,
                'phone' => $driver->phone,
            ] : null,
        ];
    }
}
