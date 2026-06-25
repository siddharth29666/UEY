<?php

namespace App\Observers;

use App\Models\Ride;
use App\Models\RideStatusLog;

class RideObserver
{
    /**
     * Handle the Ride "created" event.
     */
    public function created(Ride $ride): void
    {
        RideStatusLog::create([
            'ride_id' => $ride->id,
            'old_status' => null,
            'new_status' => $ride->status,
            'changed_by_id' => auth()->id() ?: $ride->rider_id,
            'reason' => 'Ride created',
        ]);
    }

    /**
     * Handle the Ride "updated" event.
     */
    public function updated(Ride $ride): void
    {
        if ($ride->isDirty('status')) {
            RideStatusLog::create([
                'ride_id' => $ride->id,
                'old_status' => $ride->getOriginal('status'),
                'new_status' => $ride->status,
                'changed_by_id' => auth()->id() ?: $ride->rider_id,
                'reason' => $ride->cancel_reason ?: 'Status updated',
            ]);
        }
    }
}
