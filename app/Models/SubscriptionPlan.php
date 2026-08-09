<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SubscriptionPlan extends Model
{
    use SoftDeletes;

    protected $table = 'subscription_plans';

    protected $fillable = [
        'name',
        'description',
        'price_gbp',
        'ride_credits',
        'duration_days',
        'stripe_product_id',
        'stripe_price_id',
        'status',
        'sort_order',
    ];

    protected $casts = [
        'price_gbp' => 'decimal:2',
        'ride_credits' => 'integer',
        'duration_days' => 'integer',
        'status' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(DriverSubscription::class, 'subscription_plan_id');
    }
}
