<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeviceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'device_type' => $this->device_type,
            'device_name' => $this->device_name,
            'device_token' => $this->device_token,
            'platform' => $this->platform,
            'os_version' => $this->os_version,
            'app_version' => $this->app_version,
            'language' => $this->language,
            'timezone' => $this->timezone,
            'last_used_at' => $this->last_used_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
