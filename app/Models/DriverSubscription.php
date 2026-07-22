<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DriverSubscription extends Model
{
    protected $table = 'driver_subscriptions';

    protected $fillable = [
        'driver_profile_id',
        'subscription_plan_id',
        'stripe_checkout_session_id',
        'stripe_payment_intent_id',
        'stripe_subscription_id',
        'amount_eur',
        'currency',
        'credits_allocated',
        'credits_used',
        'credits_remaining',
        'starts_at',
        'expires_at',
        'status',
    ];

    protected $casts = [
        'amount_eur' => 'decimal:2',
        'credits_allocated' => 'integer',
        'credits_used' => 'integer',
        'credits_remaining' => 'integer',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function driverProfile(): BelongsTo
    {
        return $this->belongsTo(DriverProfile::class, 'driver_profile_id');
    }

    public function subscriptionPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function creditTransactions(): HasMany
    {
        return $this->hasMany(DriverCreditTransaction::class, 'driver_subscription_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active'
            && $this->starts_at !== null
            && $this->starts_at->isPast()
            && $this->expires_at !== null
            && $this->expires_at->isFuture()
            && $this->credits_remaining > 0;
    }
}
