<?php

namespace App\Models;

use App\Enums\VehicleStatus;
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
        'total_reviews',
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
            'total_reviews' => 'integer',
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

    /**
     * Get the active approved vehicle.
     */
    public function activeVehicle(): HasOne
    {
        return $this->hasOne(Vehicle::class, 'driver_profile_id')
            ->where('status', VehicleStatus::APPROVED);
    }

    /**
     * Get the overall document status for the driver.
     */
    public function getOverallStatusAttribute(): string
    {
        $requiredTypes = [
            \App\Enums\DriverDocumentType::DRIVING_LICENSE,
            \App\Enums\DriverDocumentType::VEHICLE_REGISTRATION,
            \App\Enums\DriverDocumentType::INSURANCE,
        ];

        $uploadedDocs = $this->documents;
        $docsMap = $uploadedDocs->keyBy(function ($doc) {
            return $doc->document_type instanceof \BackedEnum ? $doc->document_type->value : (string) $doc->document_type;
        });

        $hasPending = false;
        $hasRejected = false;
        $hasExpired = false;

        foreach ($requiredTypes as $typeEnum) {
            $typeStr = $typeEnum instanceof \BackedEnum ? $typeEnum->value : (string) $typeEnum;

            if (! isset($docsMap[$typeStr])) {
                return 'missing';
            }

            $doc = $docsMap[$typeStr];

            if ($doc->expires_at && $doc->expires_at->isPast()) {
                $hasExpired = true;
            }

            if ($doc->status === \App\Enums\DocumentStatus::REJECTED) {
                $hasRejected = true;
            } elseif ($doc->status === \App\Enums\DocumentStatus::PENDING) {
                $hasPending = true;
            }
        }

        if ($hasExpired) {
            return 'expired';
        }

        if ($hasRejected) {
            return 'rejected';
        }

        if ($hasPending) {
            return 'pending';
        }

        return 'approved';
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(DriverSubscription::class, 'driver_profile_id');
    }

    public function creditTransactions(): HasMany
    {
        return $this->hasMany(DriverCreditTransaction::class, 'driver_profile_id');
    }

    public function activeSubscription(): HasOne
    {
        return $this->hasOne(DriverSubscription::class, 'driver_profile_id')
            ->where('status', 'active')
            ->where('starts_at', '<=', now())
            ->where('expires_at', '>=', now())
            ->where('credits_remaining', '>', 0)
            ->latest('id');
    }
}
