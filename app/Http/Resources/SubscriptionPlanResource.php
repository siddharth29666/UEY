<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionPlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'price_gbp' => (float) $this->price_gbp,
            'price' => (float) $this->price_gbp, // Alias for backward compatibility
            'currency' => 'GBP',
            'ride_credits' => (int) $this->ride_credits,
            'duration_days' => (int) $this->duration_days,
            'status' => (bool) $this->status,
            'sort_order' => (int) $this->sort_order,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
