<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverCreditTransaction extends Model
{
    protected $table = 'driver_credit_transactions';

    protected $fillable = [
        'driver_profile_id',
        'driver_subscription_id',
        'ride_id',
        'ride_request_id',
        'type',
        'amount',
        'balance_before',
        'balance_after',
        'reference',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'integer',
        'balance_before' => 'integer',
        'balance_after' => 'integer',
        'metadata' => 'array',
    ];

    public function driverProfile(): BelongsTo
    {
        return $this->belongsTo(DriverProfile::class, 'driver_profile_id');
    }

    public function driverSubscription(): BelongsTo
    {
        return $this->belongsTo(DriverSubscription::class, 'driver_subscription_id');
    }

    public function ride(): BelongsTo
    {
        return $this->belongsTo(Ride::class, 'ride_id');
    }

    public function rideRequest(): BelongsTo
    {
        return $this->belongsTo(RideRequest::class, 'ride_request_id');
    }
}
