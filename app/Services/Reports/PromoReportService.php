<?php

namespace App\Services\Reports;

use App\Exports\CsvExportHelper;
use App\Models\PromoCode;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PromoReportService
{
    /**
     * Get Promo Discount Summary Report.
     */
    public function getPromoDiscountReport(array $filters): array
    {
        $startDate = isset($filters['start_date']) ? Carbon::parse($filters['start_date'])->startOfDay() : Carbon::now()->startOfMonth();
        $endDate = isset($filters['end_date']) ? Carbon::parse($filters['end_date'])->endOfDay() : Carbon::now()->endOfDay();

        $query = DB::table('promo_code_usages')
            ->join('promo_codes', 'promo_code_usages.promo_code_id', '=', 'promo_codes.id')
            ->whereBetween('promo_code_usages.created_at', [$startDate, $endDate]);

        if (!empty($filters['promo_code'])) {
            $query->where('promo_codes.code', $filters['promo_code']);
        }

        $totalUses = (clone $query)->count();
        $totalDiscountAmount = (float) (clone $query)->sum('promo_code_usages.discount_amount');

        // Promo Breakdown
        $promoBreakdown = (clone $query)
            ->select(
                'promo_codes.code',
                'promo_codes.discount_type',
                'promo_codes.discount_value',
                DB::raw('COUNT(promo_code_usages.id) as uses_count'),
                DB::raw('SUM(promo_code_usages.discount_amount) as total_discount')
            )
            ->groupBy('promo_codes.id', 'promo_codes.code', 'promo_codes.discount_type', 'promo_codes.discount_value')
            ->get()
            ->map(function ($row) {
                return [
                    'promo_code' => $row->code,
                    'discount_type' => $row->discount_type,
                    'discount_value' => (float) $row->discount_value,
                    'uses_count' => (int) $row->uses_count,
                    'total_discount' => round((float) $row->total_discount, 2),
                ];
            });

        // Date Breakdown
        $dateBreakdown = (clone $query)
            ->select(
                DB::raw('date(promo_code_usages.created_at) as date'),
                DB::raw('COUNT(promo_code_usages.id) as uses_count'),
                DB::raw('SUM(promo_code_usages.discount_amount) as total_discount')
            )
            ->groupBy(DB::raw('date(promo_code_usages.created_at)'))
            ->orderBy('date', 'asc')
            ->get()
            ->map(function ($row) {
                return [
                    'date' => $row->date,
                    'uses_count' => (int) $row->uses_count,
                    'total_discount' => round((float) $row->total_discount, 2),
                ];
            });

        return [
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'summary' => [
                'total_uses' => $totalUses,
                'total_discount_amount' => round($totalDiscountAmount, 2),
            ],
            'promo_breakdown' => $promoBreakdown,
            'date_breakdown' => $dateBreakdown,
        ];
    }

    /**
     * Export Promo Discount Report as CSV.
     */
    public function exportCsv(array $reportData): StreamedResponse
    {
        $headers = [
            'Promo Code',
            'Discount Type',
            'Discount Value',
            'Total Uses',
            'Total Discount Amount',
        ];

        $rows = function () use ($reportData) {
            foreach ($reportData['promo_breakdown'] as $row) {
                yield [
                    $row['promo_code'],
                    $row['discount_type'],
                    $row['discount_value'],
                    $row['uses_count'],
                    $row['total_discount'],
                ];
            }
        };

        $filename = 'promo_discount_report_' . date('Ymd_His') . '.csv';

        return CsvExportHelper::streamCsv($filename, $headers, $rows());
    }

    /**
     * Export Promo Discount Report as Excel (.xlsx).
     */
    public function exportExcel(array $reportData): StreamedResponse
    {
        $headers = [
            'Promo Code',
            'Discount Type',
            'Discount Value',
            'Total Uses',
            'Total Discount Amount',
        ];

        $dataRows = [];
        foreach ($reportData['promo_breakdown'] as $row) {
            $dataRows[] = [
                $row['promo_code'],
                $row['discount_type'],
                $row['discount_value'],
                $row['uses_count'],
                $row['total_discount'],
            ];
        }

        return \App\Exports\ExcelExportHelper::streamXlsx('promo_discount_report', $headers, $dataRows);
    }
}
