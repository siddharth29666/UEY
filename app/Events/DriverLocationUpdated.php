<?php

namespace App\Events;

use App\Models\Ride;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DriverLocationUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $broadcastQueue = 'realtime';

    public function __construct(
        public Ride $ride,
        public float $latitude,
        public float $longitude,
        public ?float $bearing = null,
        public ?float $speed = null,
        public ?float $accuracy = null,
        public ?int $timestamp = null
    ) {}

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('ride.'.$this->ride->id),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'ride_id' => $this->ride->id,
            'coordinates' => [
                'latitude' => $this->latitude,
                'longitude' => $this->longitude,
            ],
            'heading' => $this->bearing,
            'speed' => $this->speed,
            'accuracy' => $this->accuracy,
            'timestamp' => $this->timestamp ?: time(),
        ];
    }
}
