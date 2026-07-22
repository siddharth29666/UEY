<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Enums\RideRequestStatus;
use App\Enums\RideStatus;
use App\Models\DriverProfile;
use App\Models\Payment;
use App\Models\Ride;
use App\Models\RideRequest;
use Carbon\Carbon;

class DriverDashboardService
{
    /**
     * Get dashboard metrics for the given driver profile.
     */
    public function getDashboardData(DriverProfile $driver, string $timezone = 'UTC'): array
    {
        // Timezone safe boundaries for today and current week
        $todayStart = Carbon::now($timezone)->startOfDay()->setTimezone('UTC');
        $todayEnd = Carbon::now($timezone)->endOfDay()->setTimezone('UTC');

        $weekStart = Carbon::now($timezone)->startOfWeek()->setTimezone('UTC');
        $weekEnd = Carbon::now($timezone)->endOfWeek()->setTimezone('UTC');

        // 1. Completed Rides Count
        $completedRidesCount = Ride::where('driver_profile_id', $driver->id)
            ->where('status', RideStatus::COMPLETED)
            ->count();

        // 2. Earnings calculations
        $todayEarnings = (float) Payment::where('driver_profile_id', $driver->id)
            ->where('payment_status', PaymentStatus::PAID)
            ->whereBetween('paid_at', [$todayStart, $todayEnd])
            ->sum('driver_earning');

        $weekEarnings = (float) Payment::where('driver_profile_id', $driver->id)
            ->where('payment_status', PaymentStatus::PAID)
            ->whereBetween('paid_at', [$weekStart, $weekEnd])
            ->sum('driver_earning');

        $totalEarnings = (float) Payment::where('driver_profile_id', $driver->id)
            ->where('payment_status', PaymentStatus::PAID)
            ->sum('driver_earning');

        // 3. Acceptance Rate
        $assignedRequests = RideRequest::where('driver_profile_id', $driver->id)->count();
        if ($assignedRequests === 0) {
            $acceptanceRate = 100.00;
        } else {
            $acceptedRequests = RideRequest::where('driver_profile_id', $driver->id)
                ->where('status', RideRequestStatus::ACCEPTED)
                ->count();
            $acceptanceRate = round(($acceptedRequests / $assignedRequests) * 100, 2);
        }

        // 4. On-time Rate
        $completedRides = Ride::where('driver_profile_id', $driver->id)
            ->where('status', RideStatus::COMPLETED)
            ->get(['started_at', 'arrived_at']);

        if ($completedRides->isEmpty()) {
            $ontimeRate = 100.00;
        } else {
            $thresholdMinutes = (int) app(SettingService::class)->get('on_time_threshold_minutes', 5);
            $onTimeCount = 0;
            foreach ($completedRides as $ride) {
                if ($ride->started_at && $ride->arrived_at) {
                    $diff = $ride->started_at->diffInMinutes($ride->arrived_at);
                    if ($diff <= $thresholdMinutes) {
                        $onTimeCount++;
                    }
                }
            }
            $ontimeRate = round(($onTimeCount / $completedRides->count()) * 100, 2);
        }

        $subscriptionCredits = app(DriverSubscriptionService::class)->getAvailableCredits($driver);

        return [
            'completed_rides_count' => $completedRidesCount,
            'today_earnings' => $todayEarnings,
            'week_earnings' => $weekEarnings,
            'total_earnings' => $totalEarnings,
            'acceptance_rate' => $acceptanceRate,
            'ontime_rate' => $ontimeRate,
            'subscription' => $subscriptionCredits,
        ];
    }
}
