<?php

namespace App\Listeners;

use App\Events\ReviewReceivedEvent;
use App\Events\ReviewSubmitted;
use App\Models\RideReview;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class BroadcastReview implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'realtime';

    public int $tries = 3;

    public int $timeout = 120;

    /**
     * Handle the event.
     */
    public function handle(ReviewReceivedEvent $event): void
    {
        $rideId = $event->data['ride_id'] ?? null;
        if (! $rideId) {
            return;
        }

        // Find review
        $review = RideReview::where('ride_id', $rideId)->first();
        if (! $review) {
            return;
        }

        event(new ReviewSubmitted($review));

        Log::info("BroadcastReview: ReviewSubmitted broadcast event fired for review {$review->id}.");
    }
}
