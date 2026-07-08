<?php

namespace App\Models;

use App\Enums\NotificationCategory;
use App\Enums\NotificationPriority;
use App\Enums\NotificationStatus;
use App\Enums\NotificationType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class NotificationLog extends Model
{
    use SoftDeletes;

    protected $table = 'notification_logs';

    protected $fillable = [
        'user_id',
        'title',
        'body',
        'type',
        'category',
        'priority',
        'payload',
        'status',
        'firebase_message_id',
        'failure_reason',
        'sent_at',
        'read_at',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'type' => NotificationType::class,
        'category' => NotificationCategory::class,
        'priority' => NotificationPriority::class,
        'status' => NotificationStatus::class,
        'payload' => 'array',
        'sent_at' => 'datetime',
        'read_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
