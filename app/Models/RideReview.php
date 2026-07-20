<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class RideReview extends Model
{
    use SoftDeletes;

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

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::saved(function (RideReview $review) {
            static::recalculateDriverStats($review->reviewee_id);
        });

        static::deleted(function (RideReview $review) {
            static::recalculateDriverStats($review->reviewee_id);
        });

        static::restored(function (RideReview $review) {
            static::recalculateDriverStats($review->reviewee_id);
        });
    }

    /**
     * Recalculate average rating and total review counts.
     */
    public static function recalculateDriverStats(int $revieweeUserId): void
    {
        $reviewee = User::find($revieweeUserId);
        if (!$reviewee) {
            return;
        }

        // If it's a driver, update the driver profile rating and total_reviews
        if ($reviewee->isDriver() && $reviewee->driverProfile) {
            $stats = static::where('reviewee_id', $revieweeUserId)
                ->selectRaw('COUNT(*) as total, AVG(rating) as average')
                ->first();

            $total = (int) ($stats->total ?? 0);
            $average = $total > 0 ? round((float) $stats->average, 2) : 5.00;

            $reviewee->driverProfile->update([
                'rating' => $average,
                'total_reviews' => $total,
            ]);
        } else {
            // If it's a rider, update the user rating and total_reviews
            $stats = static::where('reviewee_id', $revieweeUserId)
                ->selectRaw('COUNT(*) as total, AVG(rating) as average')
                ->first();

            $total = (int) ($stats->total ?? 0);
            $average = $total > 0 ? round((float) $stats->average, 2) : 5.00;

            $reviewee->update([
                'rating' => $average,
                'total_reviews' => $total,
            ]);
        }
    }
}
