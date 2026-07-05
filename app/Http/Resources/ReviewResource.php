<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'ride_id' => $this->ride_id,
            'reviewer_id' => $this->reviewer_id,
            'reviewer_name' => $this->is_anonymous ? 'Anonymous' : ($this->reviewer?->name ?? 'Unknown'),
            'reviewee_id' => $this->reviewee_id,
            'rating' => (int) $this->rating,
            'review' => $this->review,
            'review_tags' => $this->review_tags,
            'is_anonymous' => (bool) $this->is_anonymous,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];

        // For review list endpoints, return reviewer_name instead of exposing reviewer_id
        if (str_contains(strtolower($request->getPathInfo()), '/reviews')) {
            unset($data['reviewer_id']);
        }

        return $data;
    }
}
