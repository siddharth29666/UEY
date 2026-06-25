<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DriverDashboardResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $user = $this->user;

        return [
            'driver_profile_id' => $this->id,
            'is_online' => $this->is_online,
            'rating' => (float) $this->rating,
            'acceptance_rate' => (float) $this->acceptance_rate,
            'ontime_rate' => (float) $this->ontime_rate,
            'completed_rides_count' => 0, // Placeholder
            'earnings_summary' => [
                'today' => 0.00,
                'this_week' => 0.00,
                'total' => 0.00,
            ], // Placeholder
            'profile' => [
                'name' => $user?->name,
                'email' => $user?->email,
                'phone' => $user?->phone,
                'avatar_url' => $user?->avatar_url,
            ],
            'last_seen_at' => $this->last_seen_at?->toIso8601String(),
        ];
    }
}
