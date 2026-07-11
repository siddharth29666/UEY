<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmergencyAlertResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ride_id' => $this->ride_id,
            'user_id' => $this->user_id,
            'driver_id' => $this->driver_id,
            'status' => $this->status,
            'latitude' => (float) $this->latitude,
            'longitude' => (float) $this->longitude,
            'message' => $this->message,
            'attachment' => $this->attachment ? asset('storage/'.$this->attachment) : null,
            'attachment_type' => $this->attachment_type,
            'resolved_by' => $this->resolved_by,
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'histories' => $this->relationLoaded('histories') ? $this->histories->map(function ($h) {
                return [
                    'id' => $h->id,
                    'status' => $h->status,
                    'message' => $h->message,
                    'created_by' => $h->created_by,
                    'creator_name' => $h->creator?->name,
                    'created_at' => $h->created_at?->toIso8601String(),
                ];
            }) : [],
        ];
    }
}
