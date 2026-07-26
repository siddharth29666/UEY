<?php

namespace App\Services\Reports;

use App\Exports\CsvExportHelper;
use App\Models\Referral;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReferralReportService
{
    /**
     * Get Referral Reward Summary Report.
     */
    public function getReferralReport(array $filters): array
    {
        $startDate = isset($filters['start_date']) ? Carbon::parse($filters['start_date'])->startOfDay() : Carbon::now()->startOfMonth();
        $endDate = isset($filters['end_date']) ? Carbon::parse($filters['end_date'])->endOfDay() : Carbon::now()->endOfDay();

        $query = Referral::whereBetween('created_at', [$startDate, $endDate]);

        if (!empty($filters['user_id'])) {
            $userId = (int) $filters['user_id'];
            $query->where(function ($q) use ($userId) {
                $q->where('referrer_id', $userId)->orWhere('referred_user_id', $userId);
            });
        }

        $totalReferrals = (clone $query)->count();
        $successfulReferrals = (clone $query)->where('status', 'rewarded')->count();
        $referrerRewards = (float) (clone $query)->where('status', 'rewarded')->sum('referrer_bonus');
        $referredUserRewards = (float) (clone $query)->where('status', 'rewarded')->sum('referred_bonus');
        $totalRewardAmount = $referrerRewards + $referredUserRewards;

        // Date Breakdown
        $dateBreakdown = (clone $query)
            ->select(
                DB::raw('date(created_at) as date'),
                DB::raw('COUNT(*) as total_referrals'),
                DB::raw("SUM(CASE WHEN status = 'rewarded' THEN 1 ELSE 0 END) as successful_referrals"),
                DB::raw("SUM(CASE WHEN status = 'rewarded' THEN referrer_bonus ELSE 0 END) as referrer_rewards"),
                DB::raw("SUM(CASE WHEN status = 'rewarded' THEN referred_bonus ELSE 0 END) as referred_rewards")
            )
            ->groupBy(DB::raw('date(created_at)'))
            ->orderBy('date', 'asc')
            ->get()
            ->map(function ($row) {
                $ref = (float) $row->referrer_rewards;
                $ind = (float) $row->referred_rewards;
                return [
                    'date' => $row->date,
                    'total_referrals' => (int) $row->total_referrals,
                    'successful_referrals' => (int) $row->successful_referrals,
                    'referrer_rewards' => round($ref, 2),
                    'referred_user_rewards' => round($ind, 2),
                    'total_rewards' => round($ref + $ind, 2),
                ];
            });

        return [
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'summary' => [
                'total_referrals' => $totalReferrals,
                'successful_referrals' => $successfulReferrals,
                'referrer_rewards' => round($referrerRewards, 2),
                'referred_user_rewards' => round($referredUserRewards, 2),
                'total_reward_amount' => round($totalRewardAmount, 2),
            ],
            'date_breakdown' => $dateBreakdown,
        ];
    }

    /**
     * Export Referral Report as CSV.
     */
    public function exportCsv(array $reportData): StreamedResponse
    {
        $headers = [
            'Date',
            'Total Referrals',
            'Successful Referrals',
            'Referrer Rewards',
            'Referred User Rewards',
            'Total Rewards',
        ];

        $rows = function () use ($reportData) {
            foreach ($reportData['date_breakdown'] as $row) {
                yield [
                    $row['date'],
                    $row['total_referrals'],
                    $row['successful_referrals'],
                    $row['referrer_rewards'],
                    $row['referred_user_rewards'],
                    $row['total_rewards'],
                ];
            }
        };

        $filename = 'referral_reward_report_' . date('Ymd_His') . '.csv';

        return CsvExportHelper::streamCsv($filename, $headers, $rows());
    }

    /**
     * Export Referral Report as Excel (.xlsx).
     */
    public function exportExcel(array $reportData): StreamedResponse
    {
        $headers = [
            'Date',
            'Total Referrals',
            'Successful Referrals',
            'Referrer Rewards',
            'Referred User Rewards',
            'Total Rewards',
        ];

        $dataRows = [];
        foreach ($reportData['date_breakdown'] as $row) {
            $dataRows[] = [
                $row['date'],
                $row['total_referrals'],
                $row['successful_referrals'],
                $row['referrer_rewards'],
                $row['referred_user_rewards'],
                $row['total_rewards'],
            ];
        }

        return \App\Exports\ExcelExportHelper::streamXlsx('referral_reward_report', $headers, $dataRows);
    }
}
