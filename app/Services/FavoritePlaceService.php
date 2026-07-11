<?php

namespace App\Services;

use App\Models\FavoritePlace;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class FavoritePlaceService
{
    /**
     * Get default places for Rider Home Screen.
     */
    public function getDefaults(User $user, ?float $latitude = null, ?float $longitude = null): array
    {
        $home = FavoritePlace::where('user_id', $user->id)->where('type', 'home')->first();
        $work = FavoritePlace::where('user_id', $user->id)->where('type', 'work')->first();

        $nearestSaved = null;
        if (! is_null($latitude) && ! is_null($longitude)) {
            $savedPlaces = FavoritePlace::where('user_id', $user->id)->where('type', 'saved')->get();
            $minDistance = INF;

            foreach ($savedPlaces as $place) {
                $dist = $this->calculateDistance($place->latitude, $place->longitude, $latitude, $longitude);
                if ($dist < $minDistance) {
                    $minDistance = $dist;
                    $nearestSaved = $place;
                }
            }
        }

        return compact('home', 'work', 'nearestSaved');
    }

    /**
     * Create a new favorite place.
     */
    public function createForUser(User $user, array $data): FavoritePlace
    {
        // 1. Distance collision check (20 meters)
        $this->checkDistanceCollision($user, (float) $data['latitude'], (float) $data['longitude']);

        // 2. Single Home / Single Work check
        $type = $data['type'];
        if ($type === 'home' || $type === 'work') {
            $exists = FavoritePlace::where('user_id', $user->id)->where('type', $type)->exists();
            if ($exists) {
                throw ValidationException::withMessages([
                    'type' => ["You can only save one {$type} location. Please update your existing {$type} location."],
                ]);
            }
        }

        // 3. Clear other defaults if is_default is true
        if (! empty($data['is_default'])) {
            FavoritePlace::where('user_id', $user->id)->update(['is_default' => false]);
        }

        return FavoritePlace::create(array_merge($data, ['user_id' => $user->id]));
    }

    /**
     * Update an existing favorite place.
     */
    public function updateForUser(User $user, FavoritePlace $place, array $data): FavoritePlace
    {
        $newLat = isset($data['latitude']) ? (float) $data['latitude'] : $place->latitude;
        $newLng = isset($data['longitude']) ? (float) $data['longitude'] : $place->longitude;

        // 1. Distance collision check (excluding current place)
        if (isset($data['latitude']) || isset($data['longitude'])) {
            $this->checkDistanceCollision($user, $newLat, $newLng, $place->id);
        }

        // 2. Type validation checks
        if (isset($data['type']) && $data['type'] !== $place->type) {
            $type = $data['type'];
            if ($type === 'home' || $type === 'work') {
                $exists = FavoritePlace::where('user_id', $user->id)
                    ->where('type', $type)
                    ->where('id', '!=', $place->id)
                    ->exists();
                if ($exists) {
                    throw ValidationException::withMessages([
                        'type' => ["You can only save one {$type} location."],
                    ]);
                }
            }
        }

        // 3. Clear other defaults if is_default changes to true
        if (! empty($data['is_default']) && ! $place->is_default) {
            FavoritePlace::where('user_id', $user->id)->update(['is_default' => false]);
        }

        $place->update($data);

        return $place->fresh();
    }

    /**
     * Check if coordinates collide with existing locations.
     */
    protected function checkDistanceCollision(User $user, float $latitude, float $longitude, ?int $excludeId = null): void
    {
        $query = FavoritePlace::where('user_id', $user->id);
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        $places = $query->get();

        foreach ($places as $place) {
            $distance = $this->calculateDistance($place->latitude, $place->longitude, $latitude, $longitude);
            if ($distance <= 20.0) {
                throw ValidationException::withMessages([
                    'coordinates' => ['You already have a saved location within 20 meters of these coordinates.'],
                ]);
            }
        }
    }

    /**
     * Compute Haversine distance in meters.
     */
    public function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000; // meters

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
