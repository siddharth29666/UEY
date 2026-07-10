<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class BroadcastMessages implements ShouldQueue
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
        Log::info('BroadcastMessages: Message real-time event processed successfully.');
    }
}
