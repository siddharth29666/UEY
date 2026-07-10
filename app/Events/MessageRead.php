<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageRead implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $broadcastQueue = 'realtime';

    public function __construct(
        public Message $message
    ) {}

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        $rideId = $this->message->conversation ? $this->message->conversation->ride_id : null;
        if (! $rideId) {
            return [];
        }

        return [
            new PrivateChannel('ride.'.$rideId),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'conversation_thread_id' => $this->message->conversation_thread_id,
            'status' => $this->message->status,
            'read_at' => $this->message->read_at ? $this->message->read_at->toIso8601String() : null,
        ];
    }
}
