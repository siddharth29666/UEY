<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReferralBonusResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'amount' => (float) $this->amount,
            'type' => $this->type,
            'transaction_type' => $this->transaction_type,
            'status' => $this->status,
            'reference' => $this->reference,
            'remarks' => $this->remarks,
            'created_at' => $this->created_at ? $this->created_at->toIso8601String() : null,
        ];
    }
}
