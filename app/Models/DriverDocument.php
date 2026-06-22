<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Enums\DriverDocumentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverDocument extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'driver_documents';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'driver_profile_id',
        'document_type',
        'document_path',
        'status',
        'rejection_reason',
        'expires_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'document_type' => DriverDocumentType::class,
            'status' => DocumentStatus::class,
            'expires_at' => 'date',
        ];
    }

    /**
     * Get the driver profile that owns this document.
     */
    public function driverProfile(): BelongsTo
    {
        return $this->belongsTo(DriverProfile::class, 'driver_profile_id');
    }
}
