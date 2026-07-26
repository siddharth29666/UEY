<?php

namespace App\Services\Reports;

use App\Exports\CsvExportHelper;
use App\Models\DriverProfile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DriverEarningsReportService
{
    /**
     * Get Driver Earnings Summary report.
     */
    public function getDriverEarningsReport(array $filters): array|LengthAwarePaginator
    {
        $startDate = isset($filters['start_date']) ? Carbon::parse($filters['start_date'])->startOfDay() : Carbon::now()->startOfMonth();
        $endDate = isset($filters['end_date']) ? Carbon::parse($filters['end_date'])->endOfDay() : Carbon::now()->endOfDay();

        $query = DriverProfile::with('user');

        if (!empty($filters['driver_id'])) {
            $query->where('user_id', $filters['driver_id']);
        }

        $sortBy = $filters['sort_by'] ?? 'net_earnings';
        $sortOrder = strtolower($filters['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $perPage = (int) ($filters['per_page'] ?? 15);

        // Compute aggregated earnings per driver profile
        $results = $query->get()->map(function (DriverProfile $profile) use ($startDate, $endDate) {
            $payments = DB::table('payments')
                ->where('driver_profile_id', $profile->id)
                ->where('payment_status', 'paid')
                ->whereBetween('created_at', [$startDate, $endDate]);

            $completedRides = (clone $payments)->count();
            $grossEarnings = (float) (clone $payments)->sum('total');
            $platformCommission = (float) (clone $payments)->sum('platform_commission');
            $netEarnings = (float) (clone $payments)->sum('driver_earning');

            $wallet = DB::table('wallets')->where('user_id', $profile->user_id)->first();
            $walletId = $wallet ? $wallet->id : null;

            $cashouts = $walletId ? (float) DB::table('withdrawal_requests')
                ->where('wallet_id', $walletId)
                ->where('status', 'completed')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->sum('amount') : 0.0;

            return [
                'driver_id' => $profile->user_id,
                'driver_profile_id' => $profile->id,
                'driver_name' => $profile->user?->name ?? 'Driver #' . $profile->user_id,
                'driver_email' => $profile->user?->email,
                'completed_rides' => $completedRides,
                'gross_earnings' => round($grossEarnings, 2),
                'platform_commission' => round($platformCommission, 2),
                'net_earnings' => round($netEarnings, 2),
                'cashouts' => round($cashouts, 2),
            ];
        });

        // Sort collection
        $sorted = $results->sortBy(function ($item) use ($sortBy) {
            return $item[$sortBy] ?? $item['net_earnings'];
        }, SORT_REGULAR, $sortOrder === 'desc')->values();

        if (!empty($filters['export']) && in_array($filters['export'], ['csv', 'excel'])) {
            return $sorted->toArray();
        }

        // Manual pagination
        $page = (int) (request()->get('page', 1));
        $offset = ($page - 1) * $perPage;
        $sliced = $sorted->slice($offset, $perPage)->values();

        return new \Illuminate\Pagination\LengthAwarePaginator(
            $sliced,
            $sorted->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }

    /**
     * Export Driver Earnings Report as CSV.
     */
    public function exportCsv(array $data): StreamedResponse
    {
        $headers = [
            'Driver ID',
            'Driver Name',
            'Driver Email',
            'Completed Rides',
            'Gross Earnings',
            'Platform Commission',
            'Net Driver Earnings',
            'Completed Cashouts',
        ];

        $rows = function () use ($data) {
            foreach ($data as $row) {
                yield [
                    $row['driver_id'],
                    $row['driver_name'],
                    $row['driver_email'],
                    $row['completed_rides'],
                    $row['gross_earnings'],
                    $row['platform_commission'],
                    $row['net_earnings'],
                    $row['cashouts'],
                ];
            }
        };

        $filename = 'driver_earnings_report_' . date('Ymd_His') . '.csv';

        return CsvExportHelper::streamCsv($filename, $headers, $rows());
    }

    /**
     * Export Driver Earnings Report as Excel (.xlsx).
     */
    public function exportExcel(array $data): StreamedResponse
    {
        $headers = [
            'Driver ID',
            'Driver Name',
            'Driver Email',
            'Completed Rides',
            'Gross Earnings',
            'Platform Commission',
            'Net Driver Earnings',
            'Completed Cashouts',
        ];

        $dataRows = [];
        foreach ($data as $row) {
            $dataRows[] = [
                $row['driver_id'],
                $row['driver_name'],
                $row['driver_email'],
                $row['completed_rides'],
                $row['gross_earnings'],
                $row['platform_commission'],
                $row['net_earnings'],
                $row['cashouts'],
            ];
        }

        return \App\Exports\ExcelExportHelper::streamXlsx('driver_earnings_report', $headers, $dataRows);
    }
}
