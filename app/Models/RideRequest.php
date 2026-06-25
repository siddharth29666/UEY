<?php

namespace App\Models;

use App\Enums\RideRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RideRequest extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ride_requests';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'ride_id',
        'driver_profile_id',
        'status',
        'expires_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ride_id' => 'integer',
            'driver_profile_id' => 'integer',
            'status' => RideRequestStatus::class,
            'expires_at' => 'datetime',
        ];
    }

    /**
     * Get the ride associated with this request.
     */
    public function ride(): BelongsTo
    {
        return $this->belongsTo(Ride::class, 'ride_id');
    }

    /**
     * Get the driver profile targeted by this request.
     */
    public function driverProfile(): BelongsTo
    {
        return $this->belongsTo(DriverProfile::class, 'driver_profile_id');
    }

    /**
     * Scope query to active (non-expired) requests.
     */
    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        });
    }
}
