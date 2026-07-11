<?php

namespace App\Events;

use App\Models\EmergencyAlert;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EmergencyTriggered implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $broadcastQueue = 'realtime';

    public function __construct(public EmergencyAlert $alert) {}

    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('rider.'.$this->alert->user_id),
            new PresenceChannel('admins'),
        ];

        if ($this->alert->ride_id) {
            $channels[] = new PrivateChannel('ride.'.$this->alert->ride_id);
        }

        if ($this->alert->driver_id) {
            $channels[] = new PrivateChannel('driver.'.$this->alert->driver_id);
        }

        return $channels;
    }

    public function broadcastWith(): array
    {
        return [
            'alert' => [
                'id' => $this->alert->id,
                'ride_id' => $this->alert->ride_id,
                'user_id' => $this->alert->user_id,
                'driver_id' => $this->alert->driver_id,
                'status' => $this->alert->status,
                'latitude' => (float) $this->alert->latitude,
                'longitude' => (float) $this->alert->longitude,
                'message' => $this->alert->message,
                'created_at' => $this->alert->created_at?->toIso8601String(),
            ],
        ];
    }
}
