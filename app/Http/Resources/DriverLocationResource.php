<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DriverLocationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'driver_id' => $this->driver_id,
            'ride_id' => $this->ride_id,
            'latitude' => (float) $this->latitude,
            'longitude' => (float) $this->longitude,
            'heading' => $this->heading ? (float) $this->heading : null,
            'speed' => $this->speed ? (float) $this->speed : null,
            'accuracy' => $this->accuracy ? (float) $this->accuracy : null,
            'timestamp' => $this->timestamp,
            'created_at' => $this->created_at ? $this->created_at->toIso8601String() : null,
        ];
    }
}
