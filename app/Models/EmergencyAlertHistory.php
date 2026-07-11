<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmergencyAlertHistory extends Model
{
    protected $table = 'emergency_alert_histories';

    protected $fillable = [
        'emergency_alert_id',
        'status',
        'message',
        'created_by',
    ];

    /**
     * Get the emergency alert associated with the timeline history.
     */
    public function alert(): BelongsTo
    {
        return $this->belongsTo(EmergencyAlert::class, 'emergency_alert_id');
    }

    /**
     * Get the user who recorded the history event.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
