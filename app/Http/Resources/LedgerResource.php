<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LedgerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'wallet_transaction_id' => $this->wallet_transaction_id,
            'wallet_id' => $this->wallet_id,
            'user_id' => $this->user_id,
            'reference' => $this->reference,
            'transaction_type' => $this->transaction_type,
            'direction' => $this->direction,
            'amount' => (float) $this->amount,
            'currency' => $this->currency,
            'source' => $this->source instanceof \BackedEnum
                ? $this->source->value
                : $this->source,
            'remarks' => $this->remarks,
            'metadata' => $this->metadata ?? [],
            'created_at' => $this->created_at?->toIso8601String(),

            // Conditionally loaded relationships
            'wallet_transaction' => $this->whenLoaded('walletTransaction', function () {
                return [
                    'id' => $this->walletTransaction->id,
                    'type' => $this->walletTransaction->type,
                    'status' => $this->walletTransaction->status instanceof \BackedEnum
                        ? $this->walletTransaction->status->value
                        : $this->walletTransaction->status,
                    'balance_before' => (float) $this->walletTransaction->balance_before,
                    'balance_after' => (float) $this->walletTransaction->balance_after,
                    'payment_gateway' => $this->walletTransaction->payment_gateway,
                    'created_at' => $this->walletTransaction->created_at?->toIso8601String(),
                ];
            }),
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                    'phone' => $this->user->phone,
                    'role' => $this->user->role instanceof \BackedEnum
                        ? $this->user->role->value
                        : $this->user->role,
                ];
            }),
            'wallet' => $this->whenLoaded('wallet', function () {
                return [
                    'id' => $this->wallet->id,
                    'balance' => (float) $this->wallet->balance,
                    'currency' => $this->wallet->currency,
                    'status' => $this->wallet->status,
                ];
            }),
        ];
    }
}
