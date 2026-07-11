<?php

namespace App\Models;

use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class WalletTransaction extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'wallet_transactions';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'wallet_id',
        'type',
        'transaction_type',
        'amount',
        'balance_before',
        'balance_after',
        'status',
        'payment_gateway',
        'gateway_reference',
        'reference',
        'remarks',
        'metadata',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'wallet_id' => 'integer',
            'amount' => 'decimal:2',
            'balance_before' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'transaction_type' => WalletTransactionType::class,
            'status' => WalletTransactionStatus::class,
            'metadata' => 'array',
        ];
    }

    /**
     * Get the wallet associated with this transaction.
     */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    /**
     * Get the ledger entry associated with this transaction.
     */
    public function ledger(): HasOne
    {
        return $this->hasOne(Ledger::class, 'wallet_transaction_id');
    }
}
