<?php

namespace App\Services\Reports;

use App\Exports\CsvExportHelper;
use App\Models\Payment;
use App\Models\Ride;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RevenueReportService
{
    /**
     * Daily Revenue Report.
     */
    public function getDailyReport(string $date): array
    {
        $targetDate = Carbon::parse($date)->toDateString();

        $ridesQuery = Ride::whereDate('created_at', $targetDate);
        $totalRides = (clone $ridesQuery)->count();
        $completedRides = (clone $ridesQuery)->where('status', 'completed')->count();
        $cancelledRides = (clone $ridesQuery)->where('status', 'cancelled')->count();

        $paymentsQuery = Payment::whereDate('created_at', $targetDate);
        $grossRevenue = (float) (clone $paymentsQuery)->where('payment_status', 'paid')->sum('total');
        $platformCommission = (float) (clone $paymentsQuery)->where('payment_status', 'paid')->sum('platform_commission');
        $driverEarnings = (float) (clone $paymentsQuery)->where('payment_status', 'paid')->sum('driver_earning');
        $promoDiscounts = (float) (clone $paymentsQuery)->where('payment_status', 'paid')->sum('discount');
        $refunds = (float) (clone $paymentsQuery)->where('payment_status', 'refunded')->sum('total');
        $netRevenue = $grossRevenue - $refunds - $promoDiscounts;

        return [
            'period' => 'daily',
            'date' => $targetDate,
            'summary' => [
                'total_rides' => $totalRides,
                'completed_rides' => $completedRides,
                'cancelled_rides' => $cancelledRides,
                'gross_revenue' => round($grossRevenue, 2),
                'platform_commission' => round($platformCommission, 2),
                'driver_earnings' => round($driverEarnings, 2),
                'promo_discounts' => round($promoDiscounts, 2),
                'refunds' => round($refunds, 2),
                'net_revenue' => round($netRevenue, 2),
            ],
        ];
    }

    /**
     * Weekly Revenue Report.
     */
    public function getWeeklyReport(string $startDate, string $endDate): array
    {
        return $this->getCustomReport($startDate, $endDate, 'weekly');
    }

    /**
     * Monthly Revenue Report.
     */
    public function getMonthlyReport(int $year, int $month): array
    {
        $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth()->toDateString();
        $endDate = Carbon::createFromDate($year, $month, 1)->endOfMonth()->toDateString();

        return $this->getCustomReport($startDate, $endDate, 'monthly');
    }

    /**
     * Custom Date Range Revenue Report.
     */
    public function getCustomReport(string $startDate, string $endDate, string $periodType = 'custom'): array
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();

        $ridesQuery = Ride::whereBetween('created_at', [$start, $end]);
        $totalRides = (clone $ridesQuery)->count();
        $completedRides = (clone $ridesQuery)->where('status', 'completed')->count();
        $cancelledRides = (clone $ridesQuery)->where('status', 'cancelled')->count();

        $paymentsQuery = Payment::whereBetween('created_at', [$start, $end]);
        $grossRevenue = (float) (clone $paymentsQuery)->where('payment_status', 'paid')->sum('total');
        $platformCommission = (float) (clone $paymentsQuery)->where('payment_status', 'paid')->sum('platform_commission');
        $driverEarnings = (float) (clone $paymentsQuery)->where('payment_status', 'paid')->sum('driver_earning');
        $promoDiscounts = (float) (clone $paymentsQuery)->where('payment_status', 'paid')->sum('discount');
        $refunds = (float) (clone $paymentsQuery)->where('payment_status', 'refunded')->sum('total');
        $netRevenue = $grossRevenue - $refunds - $promoDiscounts;

        // Daily breakdown
        $dailyBreakdown = Payment::select(
            DB::raw('date(created_at) as date'),
            DB::raw('COUNT(*) as total_payments'),
            DB::raw("SUM(CASE WHEN payment_status = 'paid' THEN total ELSE 0 END) as gross_revenue"),
            DB::raw("SUM(CASE WHEN payment_status = 'paid' THEN platform_commission ELSE 0 END) as platform_commission"),
            DB::raw("SUM(CASE WHEN payment_status = 'paid' THEN driver_earning ELSE 0 END) as driver_earnings"),
            DB::raw("SUM(CASE WHEN payment_status = 'paid' THEN discount ELSE 0 END) as promo_discounts"),
            DB::raw("SUM(CASE WHEN payment_status = 'refunded' THEN total ELSE 0 END) as refunds")
        )
            ->whereBetween('created_at', [$start, $end])
            ->groupBy(DB::raw('date(created_at)'))
            ->orderBy('date', 'asc')
            ->get()
            ->map(function ($row) {
                $gross = (float) $row->gross_revenue;
                $ref = (float) $row->refunds;
                $disc = (float) $row->promo_discounts;
                return [
                    'date' => $row->date,
                    'total_payments' => (int) $row->total_payments,
                    'gross_revenue' => round($gross, 2),
                    'platform_commission' => round((float) $row->platform_commission, 2),
                    'driver_earnings' => round((float) $row->driver_earnings, 2),
                    'promo_discounts' => round($disc, 2),
                    'refunds' => round($ref, 2),
                    'net_revenue' => round($gross - $ref - $disc, 2),
                ];
            });

        return [
            'period' => $periodType,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'summary' => [
                'total_rides' => $totalRides,
                'completed_rides' => $completedRides,
                'cancelled_rides' => $cancelledRides,
                'gross_revenue' => round($grossRevenue, 2),
                'platform_commission' => round($platformCommission, 2),
                'driver_earnings' => round($driverEarnings, 2),
                'promo_discounts' => round($promoDiscounts, 2),
                'refunds' => round($refunds, 2),
                'net_revenue' => round($netRevenue, 2),
            ],
            'daily_breakdown' => $dailyBreakdown,
        ];
    }

    /**
     * Export Revenue Report as CSV.
     */
    public function exportCsv(array $reportData): StreamedResponse
    {
        $headers = [
            'Date',
            'Total Payments',
            'Gross Revenue',
            'Platform Commission',
            'Driver Earnings',
            'Promo Discounts',
            'Refunds',
            'Net Revenue',
        ];

        $rows = function () use ($reportData) {
            if (isset($reportData['daily_breakdown']) && count($reportData['daily_breakdown']) > 0) {
                foreach ($reportData['daily_breakdown'] as $row) {
                    yield [
                        $row['date'],
                        $row['total_payments'],
                        $row['gross_revenue'],
                        $row['platform_commission'],
                        $row['driver_earnings'],
                        $row['promo_discounts'],
                        $row['refunds'],
                        $row['net_revenue'],
                    ];
                }
            } else {
                $s = $reportData['summary'];
                yield [
                    $reportData['date'] ?? ($reportData['start_date'] . ' to ' . $reportData['end_date']),
                    $s['total_rides'],
                    $s['gross_revenue'],
                    $s['platform_commission'],
                    $s['driver_earnings'],
                    $s['promo_discounts'],
                    $s['refunds'],
                    $s['net_revenue'],
                ];
            }
        };

        $filename = 'revenue_report_' . date('Ymd_His') . '.csv';

        return CsvExportHelper::streamCsv($filename, $headers, $rows());
    }

    /**
     * Export Revenue Report as Excel (.xlsx).
     */
    public function exportExcel(array $reportData): StreamedResponse
    {
        $headers = [
            'Date',
            'Total Payments',
            'Gross Revenue',
            'Platform Commission',
            'Driver Earnings',
            'Promo Discounts',
            'Refunds',
            'Net Revenue',
        ];

        $dataRows = [];
        if (isset($reportData['daily_breakdown']) && count($reportData['daily_breakdown']) > 0) {
            foreach ($reportData['daily_breakdown'] as $row) {
                $dataRows[] = [
                    $row['date'],
                    $row['total_payments'],
                    $row['gross_revenue'],
                    $row['platform_commission'],
                    $row['driver_earnings'],
                    $row['promo_discounts'],
                    $row['refunds'],
                    $row['net_revenue'],
                ];
            }
        } else {
            $s = $reportData['summary'];
            $dataRows[] = [
                $reportData['date'] ?? ($reportData['start_date'] . ' to ' . $reportData['end_date']),
                $s['total_rides'],
                $s['gross_revenue'],
                $s['platform_commission'],
                $s['driver_earnings'],
                $s['promo_discounts'],
                $s['refunds'],
                $s['net_revenue'],
            ];
        }

        return \App\Exports\ExcelExportHelper::streamXlsx('revenue_report', $headers, $dataRows);
    }
}
