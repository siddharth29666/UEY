<?php

namespace App\Services;

use App\Enums\NotificationType;
use App\Enums\PaymentStatus;
use App\Enums\RideStatus;
use App\Events\DriverStatusChanged;
use App\Events\PaymentFailedEvent;
use App\Events\RideArrivedEvent;
use App\Events\RideArrivingEvent;
use App\Events\RideCompletedEvent;
use App\Events\RideStartedEvent;
use App\Models\Payment;
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
     */
    public function updateStatus(Ride $ride, string $status, array $data, User $driverUser): Ride
    {
        // 1. Authorize that the driver user owns the driver profile assigned to the ride
        $driverProfile = $driverUser->driverProfile;
        if (! $driverProfile || $ride->driver_profile_id !== $driverProfile->id) {
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

        if (! $valid) {
            throw ValidationException::withMessages([
                'status' => ["Invalid transition from {$currentStatus->value} to {$status}."],
            ]);
        }

        try {
            return DB::transaction(function () use ($ride, $status, $data, $driverUser, $driverProfile) {
                // Reload with lock for update to prevent concurrent updates
                $ride = Ride::where('id', $ride->id)->lockForUpdate()->firstOrFail();

                if ($status === 'arriving') {
                    $ride->update([
                        'status' => RideStatus::ARRIVING,
                    ]);
                    event(new RideArrivingEvent($ride->rider, NotificationType::DRIVER_ARRIVING, null, null, ['ride_id' => $ride->id]));
                } elseif ($status === 'arrived') {
                    $ride->update([
                        'status' => RideStatus::ARRIVED,
                        'arrived_at' => now(),
                    ]);
                    event(new RideArrivedEvent($ride->rider, NotificationType::DRIVER_ARRIVED, null, null, ['ride_id' => $ride->id]));
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
                    event(new RideStartedEvent($ride->rider, NotificationType::RIDE_STARTED, null, null, ['ride_id' => $ride->id]));
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

                    $originalActualFare = round($finalFare, 2);
                    $actualDiscountAmount = 0.00;
                    $finalActualFare = $originalActualFare;

                    $promoUsage = \App\Models\PromoCodeUsage::where('ride_id', $ride->id)
                        ->where('status', 'reserved')
                        ->first();

                    if ($promoUsage) {
                        $promoService = app(PromoService::class);
                        $actualDiscountAmount = $promoService->calculateDiscount($promoUsage->promoCode, $originalActualFare);
                        $finalActualFare = max(0.00, $originalActualFare - $actualDiscountAmount);

                        // Complete promo usage in database
                        $promoService->completePromo($ride->id, $originalActualFare);

                        // Audit Log for promo apply/complete
                        app(AuditLogService::class)->log(
                            $ride->rider,
                            'promo_codes',
                            'promo_apply',
                            'promo_codes',
                            $promoUsage->promo_code_id,
                            null,
                            ['ride_id' => $ride->id, 'discount_amount' => $actualDiscountAmount]
                        );
                    }

                    $ride->update([
                        'status' => RideStatus::COMPLETED,
                        'actual_distance' => $distance,
                        'actual_duration' => $duration,
                        'actual_fare' => $originalActualFare,
                        'actual_discount_amount' => $actualDiscountAmount,
                        'final_actual_fare' => $finalActualFare,
                        'completed_at' => now(),
                        'fare_breakdown' => $breakdown,
                    ]);

                    // Process payment for completed ride
                    $paymentService = app(PaymentService::class);
                    $paymentService->processPaymentForRide($ride);

                    // Detect referral first ride completion
                    app(ReferralService::class)->detectFirstRideCompletion($ride->rider);

                    // Update driver profile current location to destination and sync with Redis if online
                    $this->locationService->updateLocation(
                        $driverProfile,
                        (float) $ride->destination_latitude,
                        (float) $ride->destination_longitude
                    );
                    event(new RideCompletedEvent($ride->rider, NotificationType::RIDE_COMPLETED, null, null, ['ride_id' => $ride->id]));

                    // Dispatch status change
                    event(new DriverStatusChanged($driverUser, 'available'));
                }

                return $ride;
            });
        } catch (\Exception $e) {
            if ($status === 'completed') {
                // The transaction has rolled back!
                // We write the failed payment record outside the rolled back transaction.
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
                $subtotal = round(max($calculatedFare, $minimumFare), 2);

                $commissionRate = (float) app(SettingService::class)->get('platform_commission', config('services.payments.commission_rate', 10.0));
                $commission = round($subtotal * ($commissionRate / 100), 2);
                $driverEarning = round($subtotal - $commission, 2);

                Payment::updateOrCreate(
                    ['ride_id' => $ride->id],
                    [
                        'rider_id' => $ride->rider_id,
                        'driver_profile_id' => $ride->driver_profile_id,
                        'payment_method' => $ride->payment_method,
                        'payment_status' => PaymentStatus::FAILED,
                        'subtotal' => $subtotal,
                        'tax' => 0.00,
                        'discount' => 0.00,
                        'platform_commission' => $commission,
                        'driver_earning' => $driverEarning,
                        'total' => $subtotal,
                    ]
                );

                $ride->update([
                    'payment_status' => 'failed',
                ]);

                event(new PaymentFailedEvent($ride->rider, NotificationType::PAYMENT_FAILED, null, null, ['amount' => $subtotal, 'ride_id' => $ride->id]));
            }
            throw $e;
        }
    }
}
