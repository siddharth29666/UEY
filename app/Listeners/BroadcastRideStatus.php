<?php

namespace App\Listeners;

use App\Events\DriverArrived;
use App\Events\DriverArriving;
use App\Events\RideAccepted;
use App\Events\RideCancelled;
use App\Events\RideCompleted;
use App\Events\RideRequested;
use App\Events\RideStarted;
use App\Models\Ride;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class BroadcastRideStatus implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'realtime';

    public int $tries = 3;

    public int $timeout = 120;

    /**
     * Handle the event.
     */
    public function handle($event): void
    {
        $rideId = $event->data['ride_id'] ?? null;
        if (! $rideId) {
            return;
        }

        $ride = Ride::find($rideId);
        if (! $ride) {
            return;
        }

        $className = class_basename($event);

        switch ($className) {
            case 'RideRequestedEvent':
                event(new RideRequested($ride));
                break;
            case 'RideAcceptedEvent':
                event(new RideAccepted($ride));
                break;
            case 'RideArrivingEvent':
                event(new DriverArriving($ride));
                break;
            case 'RideArrivedEvent':
                event(new DriverArrived($ride));
                break;
            case 'RideStartedEvent':
                event(new RideStarted($ride));
                break;
            case 'RideCompletedEvent':
                event(new RideCompleted($ride));
                break;
            case 'RideCancelledEvent':
                event(new RideCancelled($ride));
                break;
        }

        Log::info("BroadcastRideStatus: Translated {$className} to broadcasting event for ride {$rideId}.");
    }
}
