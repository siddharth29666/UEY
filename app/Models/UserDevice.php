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

    public static function registerOrUpdateDevice(User $user, array $data): ?self
    {
        $token = $data['fcm_token'] ?? $data['device_token'] ?? null;
        if (empty($token)) {
            return null;
        }

        $deviceType = $data['device_type'] ?? 'android';
        $deviceName = $data['device_name'] ?? ($deviceType === 'ios' ? 'iPhone Device' : 'Android Device');
        $platform = $data['platform'] ?? $deviceType;

        return static::updateOrCreate(
            ['device_token' => $token],
            [
                'user_id' => $user->id,
                'device_type' => $deviceType,
                'device_name' => $deviceName,
                'platform' => $platform,
                'os_version' => $data['os_version'] ?? null,
                'app_version' => $data['app_version'] ?? null,
                'language' => $data['language'] ?? null,
                'timezone' => $data['timezone'] ?? null,
                'last_used_at' => now(),
            ]
        );
    }
}
