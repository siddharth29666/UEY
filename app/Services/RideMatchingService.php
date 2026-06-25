<?php

namespace App\Services;

use App\Enums\RideRequestStatus;
use App\Enums\RideStatus;
use App\Models\DriverProfile;
use App\Models\Ride;
use App\Models\RideRequest;
use App\Enums\UserStatus;
use App\Enums\VehicleStatus;

class RideMatchingService
{
    public function __construct(
        protected DriverLocationService $locationService
    ) {}

    /**
     * Match a ride with the nearest eligible online drivers and create ride requests.
     */
    public function matchDriversForRide(Ride $ride): int
    {
        $radiusKm = config('rides.matching_radius_km', 5.0);
        
        // Find nearby drivers via Redis GEOSEARCH
        $nearbyDrivers = $this->locationService->getNearbyDrivers(
            (float) $ride->pickup_latitude,
            (float) $ride->pickup_longitude,
            $radiusKm
        );

        if (empty($nearbyDrivers)) {
            return 0;
        }

        $driverIds = collect($nearbyDrivers)->pluck('driver_profile_id')->toArray();

        // Get driver profiles who are currently active in rides
        $activeDriverIdsWithRides = Ride::whereIn('driver_profile_id', $driverIds)
            ->whereIn('status', [
                RideStatus::ACCEPTED,
                RideStatus::ARRIVING,
                RideStatus::ARRIVED,
                RideStatus::IN_PROGRESS,
            ])
            ->pluck('driver_profile_id')
            ->toArray();

        $eligibleDrivers = DriverProfile::whereIn('id', $driverIds)
            ->where('is_online', true)
            ->whereNotIn('id', $activeDriverIdsWithRides)
            ->whereHas('user', function ($q) {
                $q->where('status', UserStatus::ACTIVE);
            })
            ->whereHas('vehicles', function ($q) use ($ride) {
                $q->where('status', VehicleStatus::APPROVED)
                  ->where('vehicle_type_id', $ride->vehicle_type_id);
            })
            ->get();

        // Sort in PHP memory to match Redis distance ordering (nearest first)
        $driverIdPositions = array_flip($driverIds);
        $eligibleDrivers = $eligibleDrivers->sortBy(function ($driver) use ($driverIdPositions) {
            return $driverIdPositions[$driver->id] ?? 99999;
        })->values();

        $requestExpirySeconds = config('rides.request_expiry_seconds', 30);
        $expiresAt = now()->addSeconds($requestExpirySeconds);

        $requestCount = 0;
        foreach ($eligibleDrivers as $driverProfile) {
            RideRequest::create([
                'ride_id' => $ride->id,
                'driver_profile_id' => $driverProfile->id,
                'status' => RideRequestStatus::PENDING,
                'expires_at' => $expiresAt,
            ]);
            $requestCount++;
        }

        return $requestCount;
    }
}
