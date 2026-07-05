<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ride_id' => $this->ride_id,
            'rider_id' => $this->rider_id,
            'driver_profile_id' => $this->driver_profile_id,
            'payment_method' => $this->payment_method->value,
            'payment_status' => $this->payment_status->value,
            'transaction_reference' => $this->transaction_reference,
            'subtotal' => (float) $this->subtotal,
            'tax' => (float) $this->tax,
            'discount' => (float) $this->discount,
            'platform_commission' => (float) $this->platform_commission,
            'driver_earning' => (float) $this->driver_earning,
            'total' => (float) $this->total,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
