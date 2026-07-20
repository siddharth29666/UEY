<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PromoCode extends Model
{
    use SoftDeletes;

    protected $table = 'promo_codes';

    protected $fillable = [
        'code',
        'discount_type',
        'discount_value',
        'expires_at',
        'usage_limit',
        'used_count',
        'per_user_limit',
        'min_fare',
        'max_discount',
        'is_active',
        'first_ride_only',
        'referral_coupon',
        'ride_eligibility',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'expires_at' => 'datetime',
        'usage_limit' => 'integer',
        'used_count' => 'integer',
        'per_user_limit' => 'integer',
        'min_fare' => 'decimal:2',
        'max_discount' => 'decimal:2',
        'is_active' => 'boolean',
        'first_ride_only' => 'boolean',
        'referral_coupon' => 'boolean',
        'ride_eligibility' => 'array',
    ];
}
