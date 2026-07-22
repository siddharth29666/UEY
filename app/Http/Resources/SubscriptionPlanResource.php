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
            'price_eur' => (float) $this->price_eur,
            'price' => (float) $this->price_eur, // Alias for backward compatibility
            'currency' => 'EUR',
            'ride_credits' => (int) $this->ride_credits,
            'duration_days' => (int) $this->duration_days,
            'status' => (bool) $this->status,
            'sort_order' => (int) $this->sort_order,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
