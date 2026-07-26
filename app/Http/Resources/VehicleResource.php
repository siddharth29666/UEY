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
            'vehicle_type_id' => (int) $this->vehicle_type_id,
            'make' => $this->make,
            'model' => $this->model,
            'year' => $this->year,
            'color' => $this->color,
            'plate_number' => $this->plate_number,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : (string) $this->status,
            'rejection_reason' => $this->rejection_reason,
            'driver' => $this->whenLoaded('driverProfile', function () {
                return [
                    'id' => $this->driverProfile->id,
                    'user_id' => $this->driverProfile->user_id,
                    'name' => $this->driverProfile->user?->name,
                    'email' => $this->driverProfile->user?->email,
                    'phone' => $this->driverProfile->user?->phone,
                ];
            }),
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
