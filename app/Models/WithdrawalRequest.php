<?php

namespace App\Models;

use App\Enums\WithdrawalStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WithdrawalRequest extends Model
{
    protected $table = 'withdrawal_requests';

    protected $fillable = [
        'wallet_id',
        'amount',
        'status',
        'bank_account_id',
        'admin_note',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'wallet_id' => 'integer',
            'amount' => 'decimal:2',
            'status' => WithdrawalStatus::class,
            'bank_account_id' => 'integer',
            'processed_at' => 'datetime',
        ];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }
}
