<?php

namespace App\Services;

use App\Enums\RideStatus;
use App\Models\Ride;
use Carbon\Carbon;

class TrackingService
{
    /**
     * Calculate Haversine distance in KM between two coordinates.
     */
    public function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
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

    /**
     * Calculate ETA (Estimated Arrival, Remaining Distance, Remaining Time).
     */
    public function calculateETA(Ride $ride, float $driverLat, float $driverLng, ?float $driverSpeed = null): array
    {
        $status = $ride->status;

        // If status is not in active tracking phases, return zeroed or estimated values
        if (! in_array($status, [RideStatus::ACCEPTED, RideStatus::ARRIVING, RideStatus::ARRIVED, RideStatus::IN_PROGRESS])) {
            return [
                'remaining_distance' => 0.00,
                'remaining_time' => 0,
                'estimated_arrival' => null,
            ];
        }

        // Determine destination coordinates based on ride lifecycle phase
        if ($status === RideStatus::IN_PROGRESS) {
            $destLat = (float) $ride->destination_latitude;
            $destLng = (float) $ride->destination_longitude;
        } else {
            $destLat = (float) $ride->pickup_latitude;
            $destLng = (float) $ride->pickup_longitude;
        }

        $distance = $this->calculateDistance($driverLat, $driverLng, $destLat, $destLng);

        // Estimate speed: if reported speed is valid (> 0), use it (converting m/s to km/h if needed, or keeping km/h).
        // Let's assume reported speed is in km/h or m/s. Let's use 40 km/h as a fallback.
        $speedKmh = ($driverSpeed && $driverSpeed > 0) ? $driverSpeed : 40.0;

        // Duration in hours = distance / speed
        $durationHours = $distance / $speedKmh;
        $durationMinutes = (int) ceil($durationHours * 60);

        if ($durationMinutes < 1 && $distance > 0.05) {
            $durationMinutes = 1;
        }

        $estimatedArrival = Carbon::now()->addMinutes($durationMinutes);

        return [
            'remaining_distance' => round($distance, 2),
            'remaining_time' => $durationMinutes, // in minutes
            'estimated_arrival' => $estimatedArrival->toIso8601String(),
        ];
    }

    /**
     * Generate complete tracking payload for the ride.
     */
    public function getTrackingPayload(Ride $ride): array
    {
        $driverProfile = $ride->driverProfile;

        if (! $driverProfile || is_null($driverProfile->current_latitude) || is_null($driverProfile->current_longitude)) {
            return [
                'driver' => $driverProfile ? [
                    'id' => $driverProfile->id,
                    'name' => $driverProfile->user->name,
                ] : null,
                'vehicle' => $driverProfile && $driverProfile->activeVehicle ? [
                    'make' => $driverProfile->activeVehicle->make,
                    'model' => $driverProfile->activeVehicle->model,
                    'plate' => $driverProfile->activeVehicle->plate_number,
                ] : null,
                'coordinates' => null,
                'heading' => null,
                'speed' => null,
                'eta' => [
                    'remaining_distance' => 0.0,
                    'remaining_time' => 0,
                    'estimated_arrival' => null,
                ],
                'status' => $ride->status->value,
                'last_updated' => null,
            ];
        }

        // Calculate current ETA
        $eta = $this->calculateETA(
            $ride,
            (float) $driverProfile->current_latitude,
            (float) $driverProfile->current_longitude,
            $driverProfile->speed ?? null
        );

        return [
            'driver' => [
                'id' => $driverProfile->id,
                'name' => $driverProfile->user->name,
            ],
            'vehicle' => $driverProfile->activeVehicle ? [
                'make' => $driverProfile->activeVehicle->make,
                'model' => $driverProfile->activeVehicle->model,
                'plate' => $driverProfile->activeVehicle->plate_number,
            ] : null,
            'coordinates' => [
                'latitude' => (float) $driverProfile->current_latitude,
                'longitude' => (float) $driverProfile->current_longitude,
            ],
            'heading' => $driverProfile->bearing ? (float) $driverProfile->bearing : null,
            'speed' => $driverProfile->speed ? (float) $driverProfile->speed : null,
            'eta' => $eta,
            'status' => $ride->status->value,
            'last_updated' => $driverProfile->last_located_at ? $driverProfile->last_located_at->toIso8601String() : null,
        ];
    }
}
