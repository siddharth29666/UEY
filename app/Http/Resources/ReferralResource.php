<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReferralResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'referrer_id' => $this->referrer_id,
            'referred_user_id' => $this->referred_user_id,
            'referral_code' => $this->referral_code,
            'status' => $this->status,
            'first_ride_completed_at' => $this->first_ride_completed_at ? $this->first_ride_completed_at->toIso8601String() : null,
            'referrer_bonus' => (float) $this->referrer_bonus,
            'referred_bonus' => (float) $this->referred_bonus,
            'rewarded_at' => $this->rewarded_at ? $this->rewarded_at->toIso8601String() : null,
            'created_at' => $this->created_at ? $this->created_at->toIso8601String() : null,
        ];
    }
}
