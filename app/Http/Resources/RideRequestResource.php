<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RideRequestResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ride_id' => $this->ride_id,
            'driver_profile_id' => $this->driver_profile_id,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'expires_at' => $this->expires_at ? $this->expires_at->toIso8601String() : null,
            'created_at' => $this->created_at ? $this->created_at->toIso8601String() : null,
            'updated_at' => $this->updated_at ? $this->updated_at->toIso8601String() : null,
            
            // Nested relations if loaded
            'ride' => new RideResource($this->whenLoaded('ride')),
            'driver_profile' => new DriverProfileResource($this->whenLoaded('driverProfile')),
        ];
    }
}
