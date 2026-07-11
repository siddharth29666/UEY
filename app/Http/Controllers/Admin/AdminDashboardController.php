<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\DashboardResource;
use App\Models\DriverProfile;
use App\Models\EmergencyAlert;
use App\Models\Payment;
use App\Models\Ride;
use App\Models\RideReview;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTopup;
use App\Models\WithdrawalRequest;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class AdminDashboardController extends Controller
{
    /**
     * Get dashboard summary and distribution charts.
     */
    #[OA\Get(
        path: '/admin/dashboard',
        summary: 'Admin Dashboard Analytics',
        description: 'Retrieves complete metrics summaries and chart data arrays for operations.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Dashboard'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Dashboard summaries retrieved.',
                content: new OA\JsonContent(ref: '#/components/schemas/DashboardResource')
            ),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        // 1. Metric Summaries
        $metrics = [
            'total_riders' => User::where('role', 'rider')->count(),
            'total_drivers' => User::where('role', 'driver')->count(),
            'active_drivers' => User::where('role', 'driver')->where('status', 'active')->count(),
            'online_drivers' => DriverProfile::where('is_online', true)->count(),
            'today_rides' => Ride::whereDate('created_at', Carbon::today())->count(),
            'total_rides' => Ride::count(),
            'pending_rides' => Ride::where('status', 'pending')->count(),
            'accepted_rides' => Ride::where('status', 'accepted')->count(),
            'ongoing_rides' => Ride::whereIn('status', ['arriving', 'arrived', 'in_progress'])->count(),
            'completed_rides' => Ride::where('status', 'completed')->count(),
            'cancelled_rides' => Ride::where('status', 'cancelled')->count(),
            'today_revenue' => (float) Payment::where('payment_status', 'paid')->whereDate('paid_at', Carbon::today())->sum('platform_commission'),
            'monthly_revenue' => (float) Payment::where('payment_status', 'paid')->whereMonth('paid_at', Carbon::now()->month)->whereYear('paid_at', Carbon::now()->year)->sum('platform_commission'),
            'total_wallet_balance' => (float) Wallet::sum('balance'),
            'pending_withdrawals' => WithdrawalRequest::where('status', 'pending')->count(),
            'approved_withdrawals' => WithdrawalRequest::where('status', 'completed')->count(),
            'total_reviews' => RideReview::count(),
            'average_driver_rating' => round((float) DriverProfile::avg('rating'), 2),
            'average_rider_rating' => round((float) User::where('role', 'rider')->avg('rating'), 2),
        ];

        // 1b. SOS Metric Summaries
        $resolvedAlerts = EmergencyAlert::where('status', 'resolved')
            ->whereNotNull('resolved_at')
            ->get();
        $totalSeconds = 0;
        $longestResponse = 0;
        foreach ($resolvedAlerts as $alert) {
            $diff = $alert->created_at->diffInSeconds($alert->resolved_at);
            $totalSeconds += $diff;
            if ($diff > $longestResponse) {
                $longestResponse = $diff;
            }
        }
        $avgResponseTime = $resolvedAlerts->count() > 0
            ? round($totalSeconds / $resolvedAlerts->count(), 2)
            : 0.0;

        $metrics['active_sos'] = EmergencyAlert::where('status', 'active')->count();
        $metrics['resolved_sos'] = $resolvedAlerts->count();
        $metrics['today_sos'] = EmergencyAlert::whereDate('created_at', Carbon::today())->count();
        $metrics['monthly_sos'] = EmergencyAlert::whereMonth('created_at', Carbon::now()->month)->whereYear('created_at', Carbon::now()->year)->count();
        $metrics['sos_response_time'] = $avgResponseTime;
        $metrics['longest_response'] = $longestResponse;
        $metrics['open_sos'] = EmergencyAlert::whereIn('status', ['active', 'acknowledged', 'assigned'])->count();
        $metrics['resolved_today'] = EmergencyAlert::where('status', 'resolved')->whereDate('resolved_at', Carbon::today())->count();
        $metrics['resolved_this_month'] = EmergencyAlert::where('status', 'resolved')->whereMonth('resolved_at', Carbon::now()->month)->whereYear('resolved_at', Carbon::now()->year)->count();

        // 2. Charts Data (Last 7 Days / Last 6 Months)
        $last7Days = collect(range(0, 6))->map(function ($days) {
            return Carbon::today()->subDays($days)->format('Y-m-d');
        })->reverse()->values();

        $dailyRides = [];
        $driverRegs = [];
        $riderRegs = [];
        $topups = [];

        foreach ($last7Days as $date) {
            $dailyRides[] = [
                'date' => $date,
                'count' => Ride::whereDate('created_at', $date)->count(),
            ];
            $driverRegs[] = [
                'date' => $date,
                'count' => User::where('role', 'driver')->whereDate('created_at', $date)->count(),
            ];
            $riderRegs[] = [
                'date' => $date,
                'count' => User::where('role', 'rider')->whereDate('created_at', $date)->count(),
            ];
            $topups[] = [
                'date' => $date,
                'amount' => (float) WalletTopup::where('payment_status', 'completed')->whereDate('paid_at', $date)->sum('amount'),
            ];
        }

        // Monthly Revenue (Last 6 Months)
        $monthlyRevenue = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = Carbon::today()->subMonths($i);
            $monthlyRevenue[] = [
                'month' => $month->format('Y-m'),
                'revenue' => (float) Payment::where('payment_status', 'paid')
                    ->whereMonth('paid_at', $month->month)
                    ->whereYear('paid_at', $month->year)
                    ->sum('platform_commission'),
            ];
        }

        // Ride Status Distribution
        $rideStatusDist = Ride::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->get()
            ->map(function ($item) {
                return [
                    'status' => is_object($item->status) ? $item->status->value : $item->status,
                    'count' => $item->count,
                ];
            });

        // Payment Method Distribution
        $paymentMethodDist = Ride::select('payment_method', DB::raw('count(*) as count'))
            ->groupBy('payment_method')
            ->get()
            ->map(function ($item) {
                return [
                    'payment_method' => is_object($item->payment_method) ? $item->payment_method->value : $item->payment_method,
                    'count' => $item->count,
                ];
            });

        $charts = [
            'daily_rides' => $dailyRides,
            'monthly_revenue' => $monthlyRevenue,
            'driver_registrations' => $driverRegs,
            'rider_registrations' => $riderRegs,
            'ride_status_distribution' => $rideStatusDist,
            'payment_method_distribution' => $paymentMethodDist,
            'wallet_topup_chart' => $topups,
        ];

        return response()->json([
            'success' => true,
            'data' => new DashboardResource(compact('metrics', 'charts')),
        ]);
    }
}
