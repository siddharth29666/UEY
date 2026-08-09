<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DriverSubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $daysRemaining = 0;
        if ($this->expires_at && $this->expires_at->isFuture()) {
            $daysRemaining = (int) now()->diffInDays($this->expires_at, false);
            if ($daysRemaining < 0) {
                $daysRemaining = 0;
            }
        }

        return [
            'id' => $this->id,
            'driver_profile_id' => $this->driver_profile_id,
            'subscription_plan_id' => $this->subscription_plan_id,
            'plan' => new SubscriptionPlanResource($this->whenLoaded('subscriptionPlan')),
            'amount_gbp' => (float) $this->amount_gbp,
            'currency' => strtoupper($this->currency ?? 'GBP'),
            'credits_allocated' => (int) $this->credits_allocated,
            'credits_used' => (int) $this->credits_used,
            'credits_remaining' => (int) $this->credits_remaining,
            'status' => $this->status,
            'payment_source' => $this->payment_source ?? 'wallet',
            'starts_at' => $this->starts_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'days_remaining' => $daysRemaining,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
