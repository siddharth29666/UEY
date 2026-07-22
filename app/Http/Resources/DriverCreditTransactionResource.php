<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DriverCreditTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'driver_profile_id' => $this->driver_profile_id,
            'driver_subscription_id' => $this->driver_subscription_id,
            'ride_id' => $this->ride_id,
            'ride_request_id' => $this->ride_request_id,
            'type' => $this->type,
            'amount' => (int) $this->amount,
            'balance_before' => (int) $this->balance_before,
            'balance_after' => (int) $this->balance_after,
            'reference' => $this->reference,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
