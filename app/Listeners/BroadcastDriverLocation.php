<?php

namespace App\Listeners;

use App\Events\DriverLocationUpdated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class BroadcastDriverLocation implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'realtime';

    public int $tries = 3;

    public int $timeout = 120;

    /**
     * Handle the event.
     */
    public function handle(DriverLocationUpdated $event): void
    {
        Log::info("BroadcastDriverLocation: Location broadcasted for driver {$event->ride->driverProfile->user_id} on ride {$event->ride->id}.");
    }
}
