<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WalletResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $lastTx = $this->transactions()->orderBy('id', 'desc')->first();

        return [
            'balance' => (float) $this->balance,
            'currency' => $this->currency,
            'last_transaction' => $lastTx ? new WalletTransactionResource($lastTx) : null,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
