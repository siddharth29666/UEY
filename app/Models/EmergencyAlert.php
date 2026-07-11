<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmergencyAlert extends Model
{
    protected $table = 'emergency_alerts';

    protected $fillable = [
        'ride_id',
        'user_id',
        'driver_id',
        'status',
        'latitude',
        'longitude',
        'message',
        'attachment',
        'attachment_type',
        'resolved_by',
        'resolved_at',
    ];

    protected $casts = [
        'latitude' => 'double',
        'longitude' => 'double',
        'resolved_at' => 'datetime',
    ];

    /**
     * Get the ride associated with the SOS alert.
     */
    public function ride(): BelongsTo
    {
        return $this->belongsTo(Ride::class, 'ride_id');
    }

    /**
     * Get the rider user who triggered the SOS alert.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the driver user associated with the SOS alert.
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    /**
     * Get the admin who resolved the SOS alert.
     */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * Get the timeline logs / history for the alert.
     */
    public function histories(): HasMany
    {
        return $this->hasMany(EmergencyAlertHistory::class, 'emergency_alert_id');
    }
}
