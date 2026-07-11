<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Referral extends Model
{
    protected $fillable = [
        'referrer_id',
        'referred_user_id',
        'referral_code',
        'status',
        'first_ride_completed_at',
        'referrer_bonus',
        'referred_bonus',
        'rewarded_at',
    ];

    protected $casts = [
        'first_ride_completed_at' => 'datetime',
        'rewarded_at' => 'datetime',
        'referrer_bonus' => 'decimal:2',
        'referred_bonus' => 'decimal:2',
    ];

    /**
     * Get the referrer.
     */
    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_id');
    }

    /**
     * Get the referred user (invitee).
     */
    public function referredUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_user_id');
    }
}
