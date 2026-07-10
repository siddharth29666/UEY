<?php

namespace App\Services;

use App\Models\Ride;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class RealtimeBroadcastService
{
    /**
     * Broadcast an event to a private user channel.
     */
    public function broadcastToUser(User $user, $event): void
    {
        try {
            broadcast($event)->toOthers();
        } catch (\Exception $e) {
            Log::error("RealtimeBroadcastService: Failed to broadcast to user {$user->id}: ".$e->getMessage());
        }
    }

    /**
     * Broadcast an event to a private ride channel.
     */
    public function broadcastToRide(Ride $ride, $event): void
    {
        try {
            broadcast($event)->toOthers();
        } catch (\Exception $e) {
            Log::error("RealtimeBroadcastService: Failed to broadcast to ride {$ride->id}: ".$e->getMessage());
        }
    }

    /**
     * Broadcast an event to a presence channel.
     */
    public function broadcastToPresence(string $channel, $event): void
    {
        try {
            broadcast($event)->toOthers();
        } catch (\Exception $e) {
            Log::error("RealtimeBroadcastService: Failed to broadcast to presence channel {$channel}: ".$e->getMessage());
        }
    }
}
