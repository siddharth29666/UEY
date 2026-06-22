<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VehicleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'make' => $this->make,
            'model' => $this->model,
            'year' => $this->year,
            'color' => $this->color,
            'plate_number' => $this->plate_number,
            'status' => $this->status->value,
            'vehicle_type' => $this->whenLoaded('vehicleType', function () {
                return [
                    'id' => $this->vehicleType->id,
                    'name' => $this->vehicleType->name,
                    'capacity' => $this->vehicleType->capacity,
                ];
            }),
        ];
    }
}
