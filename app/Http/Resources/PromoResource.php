<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PromoResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'discount_type' => $this->discount_type,
            'discount_value' => number_format((float) $this->discount_value, 2, '.', ''),
            'max_discount' => $this->max_discount !== null ? number_format((float) $this->max_discount, 2, '.', '') : null,
            'minimum_fare' => number_format((float) $this->min_fare, 2, '.', ''),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'first_ride_only' => (bool) $this->first_ride_only,
            'eligible' => (bool) ($this->eligible ?? false),
        ];
    }
}
