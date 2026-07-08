<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PromoCodeResource extends JsonResource
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
            'discount_value' => (float) $this->discount_value,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'usage_limit' => $this->usage_limit,
            'used_count' => $this->used_count,
            'per_user_limit' => $this->per_user_limit,
            'min_fare' => (float) $this->min_fare,
            'max_discount' => $this->max_discount !== null ? (float) $this->max_discount : null,
            'is_active' => $this->is_active,
            'first_ride_only' => $this->first_ride_only,
            'referral_coupon' => $this->referral_coupon,
            'ride_eligibility' => $this->ride_eligibility,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
