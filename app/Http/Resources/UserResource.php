<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->role->value,
            'status' => $this->status->value,
            'avatar_url' => $this->avatar_url,
            'notification_preferences' => [
                'email' => $this->email_notifications,
                'sms' => $this->sms_notifications,
                'push' => $this->push_notifications,
            ],
            'driver_profile' => new DriverProfileResource($this->whenLoaded('driverProfile')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
