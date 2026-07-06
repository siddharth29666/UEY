<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletTopup extends Model
{
    protected $table = 'wallet_topups';

    protected $fillable = [
        'wallet_id',
        'amount',
        'stripe_payment_intent',
        'payment_status',
        'gateway_response',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'wallet_id' => 'integer',
            'amount' => 'decimal:2',
            'gateway_response' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }
}
