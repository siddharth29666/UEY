<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RideResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rider_id' => $this->rider_id,
            'driver_profile_id' => $this->driver_profile_id,
            'vehicle_type_id' => $this->vehicle_type_id,
            'pickup_address' => $this->pickup_address,
            'pickup_latitude' => (float) $this->pickup_latitude,
            'pickup_longitude' => (float) $this->pickup_longitude,
            'destination_address' => $this->destination_address,
            'destination_latitude' => (float) $this->destination_latitude,
            'destination_longitude' => (float) $this->destination_longitude,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'otp' => $this->otp,
            'estimated_distance' => (float) $this->estimated_distance,
            'estimated_duration' => (int) $this->estimated_duration,
            'estimated_fare' => (float) $this->estimated_fare,
            'actual_distance' => $this->actual_distance !== null ? (float) $this->actual_distance : null,
            'actual_duration' => $this->actual_duration !== null ? (int) $this->actual_duration : null,
            'actual_fare' => $this->actual_fare !== null ? (float) $this->actual_fare : null,
            'accepted_at' => $this->accepted_at ? $this->accepted_at->toIso8601String() : null,
            'arrived_at' => $this->arrived_at ? $this->arrived_at->toIso8601String() : null,
            'started_at' => $this->started_at ? $this->started_at->toIso8601String() : null,
            'completed_at' => $this->completed_at ? $this->completed_at->toIso8601String() : null,
            'cancelled_at' => $this->cancelled_at ? $this->cancelled_at->toIso8601String() : null,
            'cancelled_by' => $this->cancelled_by,
            'cancel_reason' => $this->cancel_reason,
            'otp_verified_at' => $this->otp_verified_at ? $this->otp_verified_at->toIso8601String() : null,
            'otp_verified_by' => $this->otp_verified_by,
            'fare_breakdown' => $this->fare_breakdown,
            'created_at' => $this->created_at ? $this->created_at->toIso8601String() : null,
            'updated_at' => $this->updated_at ? $this->updated_at->toIso8601String() : null,
            
            // Nested relations if loaded
            'rider' => new UserResource($this->whenLoaded('rider')),
            'driver_profile' => new DriverProfileResource($this->whenLoaded('driverProfile')),
        ];
    }
}
