<?php

namespace App\Services;

use App\Enums\RideRequestStatus;
use App\Enums\RideStatus;
use App\Models\Ride;
use App\Models\User;
use App\Models\VehicleType;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RideService
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
     * Estimate fares for all active vehicle types.
     */
    public function estimateFares(float $pickupLat, float $pickupLng, float $destLat, float $destLng): array
    {
        $distance = $this->calculateDistance($pickupLat, $pickupLng, $destLat, $destLng);
        $duration = (int) ceil($distance * 1.5); // Estimate 1.5 mins per KM
        if ($duration < 1) {
            $duration = 1;
        }

        $vehicleTypes = VehicleType::where('active', true)->get();
        $estimates = [];

        foreach ($vehicleTypes as $type) {
            $fare = $type->base_fare + ($type->per_km_rate * $distance) + ($type->per_minute_rate * $duration);
            if ($fare < $type->minimum_fare) {
                $fare = $type->minimum_fare;
            }

            $estimates[] = [
                'vehicle_type_id' => $type->id,
                'name' => $type->name,
                'capacity' => $type->capacity,
                'estimated_distance' => round($distance, 2),
                'estimated_duration' => $duration,
                'estimated_fare' => round($fare, 2),
            ];
        }

        return $estimates;
    }

    /**
     * Create a new ride request and trigger driver matching.
     */
    public function createRide(User $rider, array $data): Ride
    {
        return DB::transaction(function () use ($rider, $data) {
            $vehicleType = VehicleType::findOrFail($data['vehicle_type_id']);

            $distance = $this->calculateDistance(
                (float) $data['pickup_latitude'],
                (float) $data['pickup_longitude'],
                (float) $data['destination_latitude'],
                (float) $data['destination_longitude']
            );
            
            $duration = (int) ceil($distance * 1.5);
            if ($duration < 1) {
                $duration = 1;
            }

            $fare = $vehicleType->base_fare + ($vehicleType->per_km_rate * $distance) + ($vehicleType->per_minute_rate * $duration);
            if ($fare < $vehicleType->minimum_fare) {
                $fare = $vehicleType->minimum_fare;
            }

            // Generate a 6-digit OTP
            $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            $ride = Ride::create([
                'rider_id' => $rider->id,
                'vehicle_type_id' => $vehicleType->id,
                'pickup_address' => $data['pickup_address'],
                'pickup_latitude' => $data['pickup_latitude'],
                'pickup_longitude' => $data['pickup_longitude'],
                'destination_address' => $data['destination_address'],
                'destination_latitude' => $data['destination_latitude'],
                'destination_longitude' => $data['destination_longitude'],
                'status' => RideStatus::PENDING,
                'otp' => $otp,
                'estimated_distance' => round($distance, 2),
                'estimated_duration' => $duration,
                'estimated_fare' => round($fare, 2),
            ]);

            // Match with nearby drivers
            $matchingService = app(RideMatchingService::class);
            $matchingService->matchDriversForRide($ride);

            return $ride;
        });
    }

    /**
     * Cancel an active ride request.
     */
    public function cancelRide(Ride $ride, User $user, ?string $reason): Ride
    {
        return DB::transaction(function () use ($ride, $user, $reason) {
            $allowedStatuses = [
                RideStatus::PENDING,
                RideStatus::ACCEPTED,
                RideStatus::ARRIVING,
                RideStatus::ARRIVED,
            ];

            if (!in_array($ride->status, $allowedStatuses)) {
                throw ValidationException::withMessages([
                    'ride' => ['Cancellation is forbidden once the ride has started, completed, or is already cancelled.']
                ]);
            }

            $ride->update([
                'status' => RideStatus::CANCELLED,
                'cancelled_at' => now(),
                'cancelled_by' => $user->role->value,
                'cancel_reason' => $reason,
            ]);

            // Expire all pending ride requests
            $ride->requests()->where('status', RideRequestStatus::PENDING)->update([
                'status' => RideRequestStatus::EXPIRED,
            ]);

            return $ride;
        });
    }
}
