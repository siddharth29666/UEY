<?php

namespace App\Services;

use App\Enums\NotificationType;
use App\Enums\RideRequestStatus;
use App\Enums\RideStatus;
use App\Events\DriverStatusChanged;
use App\Events\RideCancelledEvent;
use App\Events\RideRequestedEvent;
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
    public function estimateFares(float $pickupLat, float $pickupLng, float $destLat, float $destLng, ?User $rider = null, ?string $promoCode = null): array
    {
        $distance = $this->calculateDistance($pickupLat, $pickupLng, $destLat, $destLng);
        $duration = (int) ceil($distance * 1.5); // Estimate 1.5 mins per KM
        if ($duration < 1) {
            $duration = 1;
        }

        $vehicleTypes = VehicleType::where('active', true)->get();
        $estimates = [];

        // Pre-validate promo code globally if passed (to fail early if completely invalid)
        $promo = null;
        $promoService = app(PromoService::class);
        if ($promoCode && $rider) {
            // Find promo code
            $promo = \App\Models\PromoCode::where('code', $promoCode)->first();
            if (!$promo || !$promo->is_active || ($promo->expires_at && $promo->expires_at->isPast())) {
                throw new \Exception("Promo code is invalid or unavailable.");
            }
            // Global limit check
            $globalUsages = DB::table('promo_code_usages')
                ->where('promo_code_id', $promo->id)
                ->whereIn('status', ['reserved', 'completed'])
                ->count();
            if ($promo->usage_limit !== null && $globalUsages >= $promo->usage_limit) {
                throw new \Exception("Promo code is invalid or unavailable.");
            }
            // User usages check
            $userUsages = DB::table('promo_code_usages')
                ->where('promo_code_id', $promo->id)
                ->where('user_id', $rider->id)
                ->whereIn('status', ['reserved', 'completed'])
                ->count();
            if ($promo->per_user_limit !== null && $userUsages >= $promo->per_user_limit) {
                throw new \Exception("Promo code is invalid or unavailable.");
            }
            // First ride only check
            if ($promo->first_ride_only) {
                $hasRides = DB::table('rides')
                    ->where('rider_id', $rider->id)
                    ->where('status', 'completed')
                    ->exists();
                if ($hasRides) {
                    throw new \Exception("Promo code is invalid or unavailable.");
                }
            }
        }

        foreach ($vehicleTypes as $type) {
            $fare = $type->base_fare + ($type->per_km_rate * $distance) + ($type->per_minute_rate * $duration);
            if ($fare < $type->minimum_fare) {
                $fare = $type->minimum_fare;
            }

            $originalFare = round($fare, 2);
            $discountAmount = 0.00;
            $finalFare = $originalFare;

            if ($promo && $rider) {
                $eligible = true;
                if (!empty($promo->ride_eligibility) && !in_array($type->id, $promo->ride_eligibility)) {
                    $eligible = false;
                }
                if ($originalFare < (float) $promo->min_fare) {
                    $eligible = false;
                }

                if ($eligible) {
                    $discountAmount = $promoService->calculateDiscount($promo, $originalFare);
                    $finalFare = max(0.00, $originalFare - $discountAmount);
                }
            }

            $estimate = [
                'vehicle_type_id' => $type->id,
                'name' => $type->name,
                'capacity' => $type->capacity,
                'estimated_distance' => round($distance, 2),
                'estimated_duration' => $duration,
                'estimated_fare' => $originalFare,
            ];

            if ($promoCode) {
                $estimate['discount_amount'] = round($discountAmount, 2);
                $estimate['final_fare'] = round($finalFare, 2);
            }

            $estimates[] = $estimate;
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

            $originalFare = round($fare, 2);

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
                'estimated_fare' => $originalFare,
                'discount_amount' => 0.00,
                'final_estimated_fare' => $originalFare,
                'payment_method' => $data['payment_method'] ?? 'cash',
            ]);

            // If promo_code is provided, reserve it under database row lock
            if (!empty($data['promo_code'])) {
                $promoService = app(PromoService::class);
                $usage = $promoService->reservePromo($rider, $data['promo_code'], $vehicleType->id, $originalFare, $ride->id);

                $ride->update([
                    'discount_amount' => $usage->discount_amount,
                    'final_estimated_fare' => max(0.00, $originalFare - $usage->discount_amount),
                ]);

                // Create Audit Log for promo reserve
                app(AuditLogService::class)->log(
                    $rider,
                    'promo_codes',
                    'promo_reserve',
                    'promo_codes',
                    $usage->promo_code_id,
                    null,
                    ['ride_id' => $ride->id, 'discount_amount' => $usage->discount_amount]
                );
            }

            // Match with nearby drivers
            $matchingService = app(RideMatchingService::class);
            $matchingService->matchDriversForRide($ride);

            // Notify Rider
            event(new RideRequestedEvent($rider, NotificationType::RIDE_REQUESTED, null, null, ['ride_id' => $ride->id]));

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

            if (! in_array($ride->status, $allowedStatuses)) {
                throw ValidationException::withMessages([
                    'ride' => ['Cancellation is forbidden once the ride has started, completed, or is already cancelled.'],
                ]);
            }

            $ride->update([
                'status' => RideStatus::CANCELLED,
                'cancelled_at' => now(),
                'cancelled_by' => $user->role->value,
                'cancel_reason' => $reason,
            ]);

            // Cancel promo code usage if exists
            $promoUsage = \App\Models\PromoCodeUsage::where('ride_id', $ride->id)
                ->whereIn('status', ['reserved', 'completed'])
                ->first();
            if ($promoUsage) {
                app(PromoService::class)->cancelPromo($ride->id);

                // Audit Log for promo cancel
                app(AuditLogService::class)->log(
                    $user,
                    'promo_codes',
                    'promo_cancel',
                    'promo_codes',
                    $promoUsage->promo_code_id,
                    null,
                    ['ride_id' => $ride->id]
                );
            }

            // Expire all pending ride requests
            $ride->requests()->where('status', RideRequestStatus::PENDING)->update([
                'status' => RideRequestStatus::EXPIRED,
            ]);

            // Notify counterpart
            if ($user->isRider()) {
                if ($ride->driverProfile && $ride->driverProfile->user) {
                    event(new RideCancelledEvent($ride->driverProfile->user, NotificationType::RIDE_CANCELLED, null, null, ['ride_id' => $ride->id]));
                    event(new DriverStatusChanged($ride->driverProfile->user, 'available'));
                }
            } else {
                if ($ride->rider) {
                    event(new RideCancelledEvent($ride->rider, NotificationType::RIDE_CANCELLED, null, null, ['ride_id' => $ride->id]));
                }
                if ($ride->driverProfile && $ride->driverProfile->user) {
                    event(new DriverStatusChanged($ride->driverProfile->user, 'available'));
                }
            }

            return $ride;
        });
    }
}
