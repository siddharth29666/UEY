<?php

namespace App\Events;

use App\Models\RideReview;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReviewSubmitted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $broadcastQueue = 'realtime';

    public function __construct(
        public RideReview $review
    ) {}

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('presence-admins'),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->review->id,
            'ride_id' => $this->review->ride_id,
            'reviewer_id' => $this->review->reviewer_id,
            'reviewee_id' => $this->review->reviewee_id,
            'rating' => $this->review->rating,
            'review' => $this->review->review,
            'created_at' => $this->review->created_at->toIso8601String(),
        ];
    }
}
