<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverLocation extends Model
{
    use HasFactory;

    protected $table = 'driver_locations';

    protected $fillable = [
        'driver_id',
        'ride_id',
        'latitude',
        'longitude',
        'heading',
        'speed',
        'accuracy',
        'timestamp',
    ];

    /**
     * Get the driver user associated with this location log.
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    /**
     * Get the active ride associated with this location log.
     */
    public function ride(): BelongsTo
    {
        return $this->belongsTo(Ride::class, 'ride_id');
    }
}
