<?php

namespace App\Models;

use App\Enums\RideStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ride extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'rides';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'rider_id',
        'driver_profile_id',
        'vehicle_type_id',
        'pickup_address',
        'pickup_latitude',
        'pickup_longitude',
        'destination_address',
        'destination_latitude',
        'destination_longitude',
        'status',
        'otp',
        'estimated_distance',
        'estimated_duration',
        'estimated_fare',
        'actual_distance',
        'actual_duration',
        'actual_fare',
        'accepted_at',
        'arrived_at',
        'started_at',
        'completed_at',
        'cancelled_at',
        'cancelled_by',
        'cancel_reason',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rider_id' => 'integer',
            'driver_profile_id' => 'integer',
            'vehicle_type_id' => 'integer',
            'pickup_latitude' => 'decimal:8',
            'pickup_longitude' => 'decimal:8',
            'destination_latitude' => 'decimal:8',
            'destination_longitude' => 'decimal:8',
            'status' => RideStatus::class,
            'estimated_distance' => 'decimal:2',
            'estimated_duration' => 'integer',
            'estimated_fare' => 'decimal:2',
            'actual_distance' => 'decimal:2',
            'actual_duration' => 'integer',
            'actual_fare' => 'decimal:2',
            'accepted_at' => 'datetime',
            'arrived_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * Get the rider (user) who requested the ride.
     */
    public function rider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rider_id');
    }

    /**
     * Get the driver profile assigned to this ride.
     */
    public function driverProfile(): BelongsTo
    {
        return $this->belongsTo(DriverProfile::class, 'driver_profile_id');
    }

    /**
     * Get the vehicle category requested.
     */
    public function vehicleType(): BelongsTo
    {
        return $this->belongsTo(VehicleType::class, 'vehicle_type_id');
    }

    /**
     * Get the ride requests generated for this ride.
     */
    public function requests(): HasMany
    {
        return $this->hasMany(RideRequest::class, 'ride_id');
    }

    /**
     * Get the status logs recorded for this ride.
     */
    public function statusLogs(): HasMany
    {
        return $this->hasMany(RideStatusLog::class, 'ride_id');
    }
}
