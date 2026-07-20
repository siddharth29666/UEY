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
            'acceptance_rate' => (float) ($this->acceptance_rate ?? 100.00),
            'ontime_rate' => (float) ($this->ontime_rate ?? 100.00),
            'completed_rides_count' => (int) ($this->completed_rides_count ?? 0),
            'earnings_summary' => [
                'today' => (float) ($this->today_earnings ?? 0.00),
                'this_week' => (float) ($this->week_earnings ?? 0.00),
                'total' => (float) ($this->total_earnings ?? 0.00),
            ],
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
