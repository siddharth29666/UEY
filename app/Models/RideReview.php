<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RideReview extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ride_reviews';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'ride_id',
        'reviewer_id',
        'reviewee_id',
        'rating',
        'review',
        'review_tags',
        'is_anonymous',
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
            'reviewer_id' => 'integer',
            'reviewee_id' => 'integer',
            'rating' => 'integer',
            'review_tags' => 'array',
            'is_anonymous' => 'boolean',
        ];
    }

    /**
     * Get the ride associated with this review.
     */
    public function ride(): BelongsTo
    {
        return $this->belongsTo(Ride::class, 'ride_id');
    }

    /**
     * Get the user who submitted the review.
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    /**
     * Get the user who is reviewed.
     */
    public function reviewee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewee_id');
    }
}
