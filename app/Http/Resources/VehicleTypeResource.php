<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VehicleTypeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'capacity' => (int) $this->capacity,
            'base_fare' => number_format((float) $this->base_fare, 2, '.', ''),
            'per_km_rate' => number_format((float) $this->per_km_rate, 2, '.', ''),
            'per_minute_rate' => number_format((float) $this->per_minute_rate, 2, '.', ''),
            'minimum_fare' => number_format((float) $this->minimum_fare, 2, '.', ''),
            'commission_percentage' => number_format((float) $this->commission_percentage, 2, '.', ''),
            'icon_url' => $this->icon_url,
            'active' => (bool) $this->active,
            'deleted_at' => $this->when($request->is('api/v1/admin/*'), $this->deleted_at ? $this->deleted_at->toIso8601String() : null),
        ];
    }
}
