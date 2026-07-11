<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FavoritePlace extends Model
{
    protected $table = 'favorite_places';

    protected $fillable = [
        'user_id',
        'type',
        'label',
        'nickname',
        'google_place_id',
        'address',
        'latitude',
        'longitude',
        'is_default',
    ];

    protected $casts = [
        'latitude' => 'double',
        'longitude' => 'double',
        'is_default' => 'boolean',
    ];

    /**
     * Get user associated with the favorite place.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
