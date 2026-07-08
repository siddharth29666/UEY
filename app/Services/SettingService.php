<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class SettingService
{
    protected const CACHE_KEY = 'system_settings';

    /**
     * Get all cached settings.
     */
    public function all(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            return Setting::pluck('value', 'key')->toArray();
        });
    }

    /**
     * Get a specific setting value.
     */
    public function get(string $key, $default = null)
    {
        $settings = $this->all();
        return $settings[$key] ?? $default;
    }

    /**
     * Save multiple settings and refresh the cache.
     */
    public function save(array $settings): void
    {
        foreach ($settings as $key => $value) {
            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => is_array($value) ? json_encode($value) : (string) $value]
            );
        }
        $this->refreshCache();
    }

    /**
     * Clear settings cache.
     */
    public function refreshCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
