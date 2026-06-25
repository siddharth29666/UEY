<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DriverProfile extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'driver_profiles';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'license_number',
        'license_expiry',
        'is_online',
        'rating',
        'experience_years',
        'acceptance_rate',
        'ontime_rate',
        'total_online_hours',
        'default_navigation',
        'auto_accept',
        'current_latitude',
        'current_longitude',
        'bearing',
        'last_located_at',
        'last_seen_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'license_expiry' => 'date',
            'is_online' => 'boolean',
            'auto_accept' => 'boolean',
            'rating' => 'decimal:2',
            'experience_years' => 'decimal:1',
            'acceptance_rate' => 'decimal:2',
            'ontime_rate' => 'decimal:2',
            'total_online_hours' => 'integer',
            'current_latitude' => 'decimal:8',
            'current_longitude' => 'decimal:8',
            'bearing' => 'decimal:2',
            'last_located_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    /**
     * Get the user who owns this profile.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the documents uploaded by the driver.
     */
    public function documents(): HasMany
    {
        return $this->hasMany(DriverDocument::class, 'driver_profile_id');
    }

    /**
     * Get the vehicles owned by the driver.
     */
    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class, 'driver_profile_id');
    }

    /**
     * Get the bank account associated with this profile.
     */
    public function bankAccount(): HasOne
    {
        return $this->hasOne(DriverBankAccount::class, 'driver_profile_id');
    }
}
