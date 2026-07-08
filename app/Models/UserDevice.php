<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserDevice extends Model
{
    protected $table = 'user_devices';

    protected $fillable = [
        'user_id',
        'device_type',
        'device_name',
        'device_token',
        'platform',
        'os_version',
        'app_version',
        'language',
        'timezone',
        'last_used_at',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'last_used_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
