<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DriverProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'license_number' => $this->license_number,
            'license_expiry' => $this->license_expiry?->toDateString(),
            'is_online' => $this->is_online,
            'rating' => (float) $this->rating,
            'experience_years' => (float) $this->experience_years,
            'acceptance_rate' => (float) $this->acceptance_rate,
            'ontime_rate' => (float) $this->ontime_rate,
            'total_online_hours' => $this->total_online_hours,
            'preferences' => [
                'default_navigation' => $this->default_navigation,
                'auto_accept' => $this->auto_accept,
            ],
            'coordinates' => [
                'latitude' => $this->current_lat ? (float) $this->current_lat : null,
                'longitude' => $this->current_lng ? (float) $this->current_lng : null,
                'bearing' => $this->bearing ? (float) $this->bearing : null,
            ],
            'last_located_at' => $this->last_located_at?->toIso8601String(),
            'vehicles' => VehicleResource::collection($this->whenLoaded('vehicles')),
        ];
    }
}
