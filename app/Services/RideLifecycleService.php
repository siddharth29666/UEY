<?php

namespace App\Services;

use App\Enums\RideStatus;
use App\Models\Ride;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class RideLifecycleService
{
    public function __construct(
        protected DriverLocationService $locationService
    ) {}

    /**
     * Update the status of a ride through its execution lifecycle.
     *
     * @param Ride $ride
     * @param string $status
     * @param array $data
     * @param User $driverUser
     * @return Ride
     */
    public function updateStatus(Ride $ride, string $status, array $data, User $driverUser): Ride
    {
        // 1. Authorize that the driver user owns the driver profile assigned to the ride
        $driverProfile = $driverUser->driverProfile;
        if (!$driverProfile || $ride->driver_profile_id !== $driverProfile->id) {
            throw new AccessDeniedHttpException('You are not authorized to update this ride.');
        }

        // 2. Validate transition sequence
        $currentStatus = $ride->status;
        $valid = false;

        if ($currentStatus === RideStatus::ACCEPTED && $status === 'arriving') {
            $valid = true;
        } elseif ($currentStatus === RideStatus::ARRIVING && $status === 'arrived') {
            $valid = true;
        } elseif ($currentStatus === RideStatus::ARRIVED && $status === 'in_progress') {
            $valid = true;
        } elseif ($currentStatus === RideStatus::IN_PROGRESS && $status === 'completed') {
            $valid = true;
        }

        if (!$valid) {
            throw ValidationException::withMessages([
                'status' => ["Invalid transition from {$currentStatus->value} to {$status}."],
            ]);
        }

        return DB::transaction(function () use ($ride, $status, $data, $driverUser, $driverProfile) {
            // Reload with lock for update to prevent concurrent updates
            $ride = Ride::where('id', $ride->id)->lockForUpdate()->firstOrFail();

            if ($status === 'arriving') {
                $ride->update([
                    'status' => RideStatus::ARRIVING,
                ]);
            } elseif ($status === 'arrived') {
                $ride->update([
                    'status' => RideStatus::ARRIVED,
                    'arrived_at' => now(),
                ]);
            } elseif ($status === 'in_progress') {
                // Verify OTP
                $otp = $data['otp'] ?? null;
                if ($otp !== $ride->otp) {
                    throw ValidationException::withMessages([
                        'otp' => ['The provided OTP is invalid.'],
                    ]);
                }

                $ride->update([
                    'status' => RideStatus::IN_PROGRESS,
                    'started_at' => now(),
                    'otp_verified_at' => now(),
                    'otp_verified_by' => $driverUser->id,
                ]);
            } elseif ($status === 'completed') {
                $distance = (float) $data['actual_distance'];
                $duration = (int) $data['actual_duration'];

                $vehicleType = $ride->vehicleType;
                $baseFare = (float) $vehicleType->base_fare;
                $perKmRate = (float) $vehicleType->per_km_rate;
                $perMinuteRate = (float) $vehicleType->per_minute_rate;
                $minimumFare = (float) $vehicleType->minimum_fare;

                $distanceFare = $perKmRate * $distance;
                $durationFare = $perMinuteRate * $duration;
                $calculatedFare = $baseFare + $distanceFare + $durationFare;
                
                $appliedMinimumFare = false;
                $finalFare = $calculatedFare;
                if ($finalFare < $minimumFare) {
                    $finalFare = $minimumFare;
                    $appliedMinimumFare = true;
                }

                // Detailed breakdown
                $breakdown = [
                    'base_fare' => round($baseFare, 2),
                    'distance' => round($distance, 2),
                    'per_km_rate' => round($perKmRate, 2),
                    'distance_fare' => round($distanceFare, 2),
                    'duration' => $duration,
                    'per_minute_rate' => round($perMinuteRate, 2),
                    'duration_fare' => round($durationFare, 2),
                    'calculated_fare' => round($calculatedFare, 2),
                    'minimum_fare' => round($minimumFare, 2),
                    'applied_minimum_fare' => $appliedMinimumFare,
                    'final_fare' => round($finalFare, 2),
                ];

                $ride->update([
                    'status' => RideStatus::COMPLETED,
                    'actual_distance' => $distance,
                    'actual_duration' => $duration,
                    'actual_fare' => round($finalFare, 2),
                    'completed_at' => now(),
                    'fare_breakdown' => $breakdown,
                ]);

                // Update driver profile current location to destination and sync with Redis if online
                $this->locationService->updateLocation(
                    $driverProfile,
                    (float) $ride->destination_latitude,
                    (float) $ride->destination_longitude
                );
            }

            return $ride;
        });
    }
}
