<?php

namespace App\Models;

use App\Enums\LedgerSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ledger extends Model
{
    protected $table = 'ledgers';

    protected $fillable = [
        'wallet_transaction_id',
        'wallet_id',
        'user_id',
        'reference',
        'transaction_type',
        'direction',
        'amount',
        'currency',
        'source',
        'remarks',
        'metadata',
    ];

    protected $casts = [
        'wallet_transaction_id' => 'integer',
        'wallet_id' => 'integer',
        'user_id' => 'integer',
        'amount' => 'decimal:2',
        'metadata' => 'array',
        'source' => LedgerSource::class,
    ];

    /**
     * Boot the model.
     * Enforce immutability on update and delete.
     */
    protected static function boot()
    {
        parent::boot();

        static::updating(function ($model) {
            throw new \RuntimeException('Ledger records are immutable and cannot be updated.');
        });

        static::deleting(function ($model) {
            throw new \RuntimeException('Ledger records are immutable and cannot be deleted.');
        });
    }

    /**
     * Get the wallet associated with this ledger entry.
     */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    /**
     * Get the user associated with this ledger entry.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the wallet transaction associated with this ledger entry.
     */
    public function walletTransaction(): BelongsTo
    {
        return $this->belongsTo(WalletTransaction::class, 'wallet_transaction_id');
    }
}
