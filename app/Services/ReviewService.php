<?php

namespace App\Services;

use App\Enums\RideStatus;
use App\Models\Ride;
use App\Models\RideReview;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class ReviewService
{
    /**
     * Submit a new rating & review for a completed ride.
     */
    public function submitReview(Ride $ride, User $reviewer, array $data): RideReview
    {
        // 1. Validation: Ride must be completed
        if ($ride->status !== RideStatus::COMPLETED) {
            throw ValidationException::withMessages([
                'ride' => ['Reviews are only allowed for completed rides.']
            ]);
        }

        // 2. Validation: Soft deleted users cannot review
        if ($reviewer->trashed()) {
            throw new AccessDeniedHttpException('Soft deleted users cannot submit reviews.');
        }

        // 3. Validation: Reviewer must be a ride participant
        $isRider = ($ride->rider_id === $reviewer->id);
        $isDriver = ($ride->driverProfile && $ride->driverProfile->user_id === $reviewer->id);

        if (!$isRider && !$isDriver) {
            throw new AccessDeniedHttpException('You are not authorized to review this ride.');
        }

        // 4. Validation: Only one review per participant per ride
        $exists = RideReview::where('ride_id', $ride->id)
            ->where('reviewer_id', $reviewer->id)
            ->exists();
        if ($exists) {
            throw ValidationException::withMessages([
                'review' => ['You have already reviewed this ride.']
            ]);
        }

        // 5. Determine Reviewee
        $revieweeId = $isRider 
            ? $ride->driverProfile->user_id 
            : $ride->rider_id;

        return DB::transaction(function () use ($ride, $reviewer, $revieweeId, $data) {
            // Create review record
            $review = RideReview::create([
                'ride_id' => $ride->id,
                'reviewer_id' => $reviewer->id,
                'reviewee_id' => $revieweeId,
                'rating' => $data['rating'],
                'review' => $data['review'] ?? null,
                'review_tags' => $data['review_tags'] ?? null,
                'is_anonymous' => $data['is_anonymous'] ?? false,
            ]);

            // Recalculate average rating incrementally
            $reviewee = User::findOrFail($revieweeId);
            if ($reviewee->isDriver()) {
                $profile = $reviewee->driverProfile;
                if ($profile) {
                    $currentTotal = $profile->total_reviews;
                    $currentAvg = (float) $profile->rating;
                    $newTotal = $currentTotal + 1;
                    $newAvg = (($currentAvg * $currentTotal) + $data['rating']) / $newTotal;

                    $profile->update([
                        'rating' => round($newAvg, 2),
                        'total_reviews' => $newTotal,
                    ]);
                }
            } else {
                $currentTotal = $reviewee->total_reviews;
                $currentAvg = (float) $reviewee->rating;
                $newTotal = $currentTotal + 1;
                $newAvg = (($currentAvg * $currentTotal) + $data['rating']) / $newTotal;

                $reviewee->update([
                    'rating' => round($newAvg, 2),
                    'total_reviews' => $newTotal,
                ]);
            }

            return $review;
        });

        // Notify Reviewee
        $reviewee = \App\Models\User::findOrFail($revieweeId);
        event(new \App\Events\ReviewReceivedEvent($reviewee, \App\Enums\NotificationType::REVIEW_RECEIVED, null, null, ['rating' => $review->rating, 'ride_id' => $ride->id]));

        return $review;
    }

    /**
     * Get both reviews for a specific ride.
     */
    public function getRideReviews(Ride $ride, User $user): array
    {
        // Must be participant
        $isRider = ($ride->rider_id === $user->id);
        $isDriver = ($ride->driverProfile && $ride->driverProfile->user_id === $user->id);

        if (!$isRider && !$isDriver) {
            throw new AccessDeniedHttpException('You are not authorized to view reviews for this ride.');
        }

        $riderReview = RideReview::where('ride_id', $ride->id)
            ->where('reviewer_id', $ride->rider_id)
            ->first();

        $driverUserId = $ride->driverProfile ? $ride->driverProfile->user_id : null;
        $driverReview = $driverUserId
            ? RideReview::where('ride_id', $ride->id)->where('reviewer_id', $driverUserId)->first()
            : null;

        return compact('riderReview', 'driverReview');
    }
}
