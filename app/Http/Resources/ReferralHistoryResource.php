<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReferralHistoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'referred_user' => [
                'id' => $this->referredUser?->id,
                'name' => $this->referredUser?->name,
                'phone' => $this->referredUser?->phone,
                'email' => $this->referredUser?->email,
            ],
            'status' => $this->status,
            'first_ride_completed' => (bool) $this->first_ride_completed_at,
            'first_ride_completed_at' => $this->first_ride_completed_at ? $this->first_ride_completed_at->toIso8601String() : null,
            'created_at' => $this->created_at ? $this->created_at->toIso8601String() : null,
        ];
    }
}
