<?php

namespace App\Services\Reports;

use App\Exports\CsvExportHelper;
use App\Models\Payment;
use App\Models\Ride;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CommissionReportService
{
    /**
     * Get Platform Commission Report.
     */
    public function getCommissionReport(array $filters): array
    {
        $startDate = isset($filters['start_date']) ? Carbon::parse($filters['start_date'])->startOfDay() : Carbon::now()->startOfMonth();
        $endDate = isset($filters['end_date']) ? Carbon::parse($filters['end_date'])->endOfDay() : Carbon::now()->endOfDay();

        $query = Payment::whereBetween('payments.created_at', [$startDate, $endDate])
            ->where('payments.payment_status', 'paid');

        if (!empty($filters['driver_id'])) {
            $query->where('payments.driver_profile_id', function ($sub) use ($filters) {
                $sub->select('id')->from('driver_profiles')->where('user_id', $filters['driver_id']);
            });
        }

        if (!empty($filters['vehicle_type_id'])) {
            $query->join('rides', 'payments.ride_id', '=', 'rides.id')
                ->where('rides.vehicle_type_id', $filters['vehicle_type_id']);
        }

        $totalCompletedRides = (clone $query)->count();
        $grossRideAmount = (float) (clone $query)->sum('payments.total');
        $platformCommission = (float) (clone $query)->sum('payments.platform_commission');
        $driverEarnings = (float) (clone $query)->sum('payments.driver_earning');

        // Date-wise breakdown
        $dateBreakdown = (clone $query)
            ->select(
                DB::raw('date(payments.created_at) as date'),
                DB::raw('COUNT(*) as ride_count'),
                DB::raw('SUM(payments.total) as gross_amount'),
                DB::raw('SUM(payments.platform_commission) as commission'),
                DB::raw('SUM(payments.driver_earning) as driver_earnings')
            )
            ->groupBy(DB::raw('date(payments.created_at)'))
            ->orderBy('date', 'asc')
            ->get()
            ->map(function ($row) {
                return [
                    'date' => $row->date,
                    'ride_count' => (int) $row->ride_count,
                    'gross_amount' => round((float) $row->gross_amount, 2),
                    'platform_commission' => round((float) $row->commission, 2),
                    'driver_earnings' => round((float) $row->driver_earnings, 2),
                ];
            });

        return [
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'summary' => [
                'total_completed_rides' => $totalCompletedRides,
                'gross_ride_amount' => round($grossRideAmount, 2),
                'platform_commission' => round($platformCommission, 2),
                'driver_earnings' => round($driverEarnings, 2),
                'effective_commission_rate' => $grossRideAmount > 0 ? round(($platformCommission / $grossRideAmount) * 100, 2) . '%' : '0%',
            ],
            'date_breakdown' => $dateBreakdown,
        ];
    }

    /**
     * Export Commission Report as CSV.
     */
    public function exportCsv(array $reportData): StreamedResponse
    {
        $headers = [
            'Date',
            'Completed Rides',
            'Gross Ride Amount',
            'Platform Commission',
            'Driver Earnings',
        ];

        $rows = function () use ($reportData) {
            foreach ($reportData['date_breakdown'] as $row) {
                yield [
                    $row['date'],
                    $row['ride_count'],
                    $row['gross_amount'],
                    $row['platform_commission'],
                    $row['driver_earnings'],
                ];
            }
        };

        $filename = 'platform_commission_report_' . date('Ymd_His') . '.csv';

        return CsvExportHelper::streamCsv($filename, $headers, $rows());
    }

    /**
     * Export Platform Commission Report as Excel (.xlsx).
     */
    public function exportExcel(array $reportData): StreamedResponse
    {
        $headers = [
            'Date',
            'Completed Rides',
            'Gross Ride Amount',
            'Platform Commission',
            'Driver Earnings',
        ];

        $dataRows = [];
        foreach ($reportData['date_breakdown'] as $row) {
            $dataRows[] = [
                $row['date'],
                $row['ride_count'],
                $row['gross_amount'],
                $row['platform_commission'],
                $row['driver_earnings'],
            ];
        }

        return \App\Exports\ExcelExportHelper::streamXlsx('platform_commission_report', $headers, $dataRows);
    }
}
