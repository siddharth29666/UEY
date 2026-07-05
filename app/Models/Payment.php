<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'payments';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'ride_id',
        'rider_id',
        'driver_profile_id',
        'payment_method',
        'payment_status',
        'transaction_reference',
        'subtotal',
        'tax',
        'discount',
        'platform_commission',
        'driver_earning',
        'total',
        'paid_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ride_id' => 'integer',
            'rider_id' => 'integer',
            'driver_profile_id' => 'integer',
            'payment_method' => PaymentMethod::class,
            'payment_status' => PaymentStatus::class,
            'subtotal' => 'decimal:2',
            'tax' => 'decimal:2',
            'discount' => 'decimal:2',
            'platform_commission' => 'decimal:2',
            'driver_earning' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * Get the ride associated with this payment.
     */
    public function ride(): BelongsTo
    {
        return $this->belongsTo(Ride::class);
    }

    /**
     * Get the rider associated with this payment.
     */
    public function rider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rider_id');
    }

    /**
     * Get the driver profile associated with this payment.
     */
    public function driverProfile(): BelongsTo
    {
        return $this->belongsTo(DriverProfile::class, 'driver_profile_id');
    }
}
