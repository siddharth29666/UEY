<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    protected $table = 'notification_preferences';

    protected $fillable = [
        'user_id',
        'ride_notifications',
        'wallet_notifications',
        'payment_notifications',
        'promotion_notifications',
        'system_notifications',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'ride_notifications' => 'boolean',
        'wallet_notifications' => 'boolean',
        'payment_notifications' => 'boolean',
        'promotion_notifications' => 'boolean',
        'system_notifications' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
