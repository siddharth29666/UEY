<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcast
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
            'sender_id' => $this->message->sender_id,
            'message' => $this->message->message,
            'type' => $this->message->type,
            'status' => $this->message->status,
            'created_at' => $this->message->created_at->toIso8601String(),
        ];
    }
}
