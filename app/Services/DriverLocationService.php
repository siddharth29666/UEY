<?php

namespace App\Services;

use App\Models\DriverProfile;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

class DriverLocationService
{
    protected string $redisKey = 'drivers:locations';

    /**
     * Toggle online/offline status for a driver and update Redis GEO index.
     */
    public function toggleOnlineStatus(DriverProfile $driverProfile, bool $online): void
    {
        $driverProfile->update([
            'is_online' => $online,
            'last_seen_at' => now(),
        ]);

        try {
            if ($online) {
                // Only add to Redis if coordinates are present
                if (!is_null($driverProfile->current_longitude) && !is_null($driverProfile->current_latitude)) {
                    Redis::geoadd(
                        $this->redisKey,
                        (float) $driverProfile->current_longitude,
                        (float) $driverProfile->current_latitude,
                        (string) $driverProfile->id
                    );
                }
            } else {
                Redis::zrem($this->redisKey, (string) $driverProfile->id);
            }
        } catch (\Exception $e) {
            Log::error("Redis connection failed during online status toggle: " . $e->getMessage());
        }
    }

    /**
     * Update driver location and sync with Redis GEO index if online.
     */
    public function updateLocation(DriverProfile $driverProfile, float $latitude, float $longitude, ?float $bearing = null): void
    {
        $driverProfile->update([
            'current_latitude' => $latitude,
            'current_longitude' => $longitude,
            'bearing' => $bearing,
            'last_located_at' => now(),
            'last_seen_at' => now(),
        ]);

        try {
            if ($driverProfile->is_online) {
                Redis::geoadd(
                    $this->redisKey,
                    $longitude,
                    $latitude,
                    (string) $driverProfile->id
                );
            }
        } catch (\Exception $e) {
            Log::error("Redis connection failed during location update: " . $e->getMessage());
        }
    }

    /**
     * Retrieve nearby online drivers using Redis GEOSEARCH.
     * Default radius is 5 KM.
     */
    public function getNearbyDrivers(float $latitude, float $longitude, float $radiusKm = 5.0): array
    {
        try {
            // Predis command pattern for GEOSEARCH
            // syntax: GEOSEARCH key FROMLONLAT lng lat BYRADIUS radius unit [WITHCOORD] [WITHDIST] [WITHHASH] [ASC|DESC]
            // We use executeRaw to be compatible across various predis client versions.
            $results = Redis::executeRaw([
                'GEOSEARCH',
                $this->redisKey,
                'FROMLONLAT',
                (string) $longitude,
                (string) $latitude,
                'BYRADIUS',
                (string) $radiusKm,
                'km',
                'WITHDIST',
                'WITHCOORD'
            ]);

            if (!is_array($results)) {
                return [];
            }

            $nearbyDrivers = [];
            foreach ($results as $result) {
                if (is_array($result) && count($result) >= 3) {
                    $driverId = $result[0];
                    $distance = $result[1];
                    $coords = $result[2]; // [lng, lat]

                    $nearbyDrivers[] = [
                        'driver_profile_id' => (int) $driverId,
                        'distance' => (float) $distance,
                        'latitude' => (float) $coords[1],
                        'longitude' => (float) $coords[0],
                    ];
                }
            }

            return $nearbyDrivers;

        } catch (\Exception $e) {
            Log::warning("Redis connection failed during nearby driver search: " . $e->getMessage() . ". Falling back to database-based matching.");

            try {
                // Fetch all online driver profiles with coordinates
                $onlineDrivers = DriverProfile::where('is_online', true)
                    ->whereNotNull('current_latitude')
                    ->whereNotNull('current_longitude')
                    ->get();

                $nearbyDrivers = [];
                foreach ($onlineDrivers as $driver) {
                    $distance = $this->calculateHaversineDistance(
                        $latitude,
                        $longitude,
                        (float) $driver->current_latitude,
                        (float) $driver->current_longitude
                    );

                    if ($distance <= $radiusKm) {
                        $nearbyDrivers[] = [
                            'driver_profile_id' => (int) $driver->id,
                            'distance' => (float) $distance,
                            'latitude' => (float) $driver->current_latitude,
                            'longitude' => (float) $driver->current_longitude,
                        ];
                    }
                }

                // Sort by distance ascending (nearest first)
                usort($nearbyDrivers, function ($a, $b) {
                    return $a['distance'] <=> $b['distance'];
                });

                return $nearbyDrivers;
            } catch (\Exception $dbEx) {
                Log::error("Database fallback matching failed: " . $dbEx->getMessage());
                return [];
            }
        }
    }

    /**
     * Calculate Haversine distance in KM between two coordinates.
     */
    protected function calculateHaversineDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371.0; // KM

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
