<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Reports\CashoutReportRequest;
use App\Http\Requests\Admin\Reports\CommissionReportRequest;
use App\Http\Requests\Admin\Reports\DriverEarningsReportRequest;
use App\Http\Requests\Admin\Reports\LedgerReportRequest;
use App\Http\Requests\Admin\Reports\PromoReportRequest;
use App\Http\Requests\Admin\Reports\ReferralReportRequest;
use App\Http\Requests\Admin\Reports\RevenueReportRequest;
use App\Http\Requests\Admin\Reports\WalletReportRequest;
use App\Services\Reports\CommissionReportService;
use App\Services\Reports\DriverEarningsReportService;
use App\Services\Reports\PromoReportService;
use App\Services\Reports\ReferralReportService;
use App\Services\Reports\RevenueReportService;
use App\Services\Reports\WalletReportService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminReportController extends Controller
{
    public function __construct(
        protected RevenueReportService $revenueService,
        protected CommissionReportService $commissionService,
        protected DriverEarningsReportService $driverEarningsService,
        protected PromoReportService $promoService,
        protected ReferralReportService $referralService,
        protected WalletReportService $walletReportService
    ) {}

    /**
     * Daily Revenue Report
     */
    #[OA\Get(
        path: '/admin/reports/revenue/daily',
        summary: 'Admin — Daily Revenue Report',
        description: 'Returns revenue, rides summary, commission, and driver earnings for a selected day.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Reports'],
        parameters: [
            new OA\Parameter(name: 'date', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date', example: '2026-07-26')),
            new OA\Parameter(name: 'export', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['csv', 'excel'])),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Daily revenue report retrieved successfully.'),
        ]
    )]
    public function dailyRevenue(RevenueReportRequest $request): JsonResponse|StreamedResponse
    {
        $date = $request->query('date', Carbon::today()->toDateString());
        $data = $this->revenueService->getDailyReport($date);

        if ($request->query('export') === 'excel') {
            return $this->revenueService->exportExcel($data);
        }
        if ($request->query('export') === 'csv') {
            return $this->revenueService->exportCsv($data);
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Weekly Revenue Report
     */
    #[OA\Get(
        path: '/admin/reports/revenue/weekly',
        summary: 'Admin — Weekly Revenue Report',
        description: 'Returns weekly day-by-day revenue breakdown and metrics.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Reports'],
        parameters: [
            new OA\Parameter(name: 'start_date', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'end_date', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'export', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['csv', 'excel'])),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Weekly revenue report retrieved successfully.'),
        ]
    )]
    public function weeklyRevenue(RevenueReportRequest $request): JsonResponse|StreamedResponse
    {
        $startDate = $request->query('start_date', Carbon::now()->startOfWeek()->toDateString());
        $endDate = $request->query('end_date', Carbon::now()->endOfWeek()->toDateString());

        $data = $this->revenueService->getWeeklyReport($startDate, $endDate);

        if ($request->query('export') === 'excel') {
            return $this->revenueService->exportExcel($data);
        }
        if ($request->query('export') === 'csv') {
            return $this->revenueService->exportCsv($data);
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Monthly Revenue Report
     */
    #[OA\Get(
        path: '/admin/reports/revenue/monthly',
        summary: 'Admin — Monthly Revenue Report',
        description: 'Returns monthly day-by-day revenue breakdown and metrics.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Reports'],
        parameters: [
            new OA\Parameter(name: 'year', in: 'query', required: false, schema: new OA\Schema(type: 'integer', example: 2026)),
            new OA\Parameter(name: 'month', in: 'query', required: false, schema: new OA\Schema(type: 'integer', example: 7)),
            new OA\Parameter(name: 'export', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['csv', 'excel'])),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Monthly revenue report retrieved successfully.'),
        ]
    )]
    public function monthlyRevenue(RevenueReportRequest $request): JsonResponse|StreamedResponse
    {
        $year = (int) $request->query('year', Carbon::now()->year);
        $month = (int) $request->query('month', Carbon::now()->month);

        $data = $this->revenueService->getMonthlyReport($year, $month);

        if ($request->query('export') === 'excel') {
            return $this->revenueService->exportExcel($data);
        }
        if ($request->query('export') === 'csv') {
            return $this->revenueService->exportCsv($data);
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Custom Date Range Revenue Report
     */
    #[OA\Get(
        path: '/admin/reports/revenue/custom',
        summary: 'Admin — Custom Date Range Revenue Report',
        description: 'Returns custom date range revenue report with day-by-day breakdown.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Reports'],
        parameters: [
            new OA\Parameter(name: 'start_date', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'end_date', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'export', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['csv', 'excel'])),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Custom revenue report retrieved successfully.'),
            new OA\Response(response: 422, description: 'Validation error.'),
        ]
    )]
    public function customRevenue(RevenueReportRequest $request): JsonResponse|StreamedResponse
    {
        $startDate = $request->validated()['start_date'];
        $endDate = $request->validated()['end_date'];

        $data = $this->revenueService->getCustomReport($startDate, $endDate, 'custom');

        if ($request->query('export') === 'excel') {
            return $this->revenueService->exportExcel($data);
        }
        if ($request->query('export') === 'csv') {
            return $this->revenueService->exportCsv($data);
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Platform Commission Report
     */
    #[OA\Get(
        path: '/admin/reports/platform-commission',
        summary: 'Admin — Platform Commission Report',
        description: 'Returns platform commission earnings filtered by date, driver, or vehicle type.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Reports'],
        parameters: [
            new OA\Parameter(name: 'start_date', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'end_date', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'driver_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'vehicle_type_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'export', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['csv', 'excel'])),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Platform commission report retrieved successfully.'),
        ]
    )]
    public function platformCommission(CommissionReportRequest $request): JsonResponse|StreamedResponse
    {
        $filters = $request->validated();
        $data = $this->commissionService->getCommissionReport($filters);

        if ($request->query('export') === 'excel') {
            return $this->commissionService->exportExcel($data);
        }
        if ($request->query('export') === 'csv') {
            return $this->commissionService->exportCsv($data);
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Driver Earnings Summary
     */
    #[OA\Get(
        path: '/admin/reports/driver-earnings',
        summary: 'Admin — Driver Earnings Summary Report',
        description: 'Returns driver earnings summary with pagination, date filtering, and sorting.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Reports'],
        parameters: [
            new OA\Parameter(name: 'start_date', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'end_date', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'driver_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'sort_by', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['net_earnings', 'completed_rides', 'gross_earnings'])),
            new OA\Parameter(name: 'sort_order', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['asc', 'desc'])),
            new OA\Parameter(name: 'export', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['csv', 'excel'])),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Driver earnings summary retrieved successfully.'),
        ]
    )]
    public function driverEarnings(DriverEarningsReportRequest $request): JsonResponse|StreamedResponse
    {
        $filters = $request->validated();
        $data = $this->driverEarningsService->getDriverEarningsReport($filters);

        if ($request->query('export') === 'excel') {
            return $this->driverEarningsService->exportExcel($data);
        }
        if ($request->query('export') === 'csv') {
            return $this->driverEarningsService->exportCsv($data);
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Promo Discount Summary
     */
    #[OA\Get(
        path: '/admin/reports/promo-discounts',
        summary: 'Admin — Promo Discount Summary Report',
        description: 'Returns summary of promo code usage, discounts applied, and revenue impact.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Reports'],
        parameters: [
            new OA\Parameter(name: 'start_date', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'end_date', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'promo_code', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'export', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['csv', 'excel'])),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Promo discount report retrieved successfully.'),
        ]
    )]
    public function promoDiscounts(PromoReportRequest $request): JsonResponse|StreamedResponse
    {
        $filters = $request->validated();
        $data = $this->promoService->getPromoDiscountReport($filters);

        if ($request->query('export') === 'excel') {
            return $this->promoService->exportExcel($data);
        }
        if ($request->query('export') === 'csv') {
            return $this->promoService->exportCsv($data);
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Referral Reward Summary
     */
    #[OA\Get(
        path: '/admin/reports/referral-rewards',
        summary: 'Admin — Referral Reward Summary Report',
        description: 'Returns summary of total referrals, successful invites, and referrer/referred user payouts.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Reports'],
        parameters: [
            new OA\Parameter(name: 'start_date', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'end_date', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'user_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'export', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['csv', 'excel'])),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Referral reward report retrieved successfully.'),
        ]
    )]
    public function referralRewards(ReferralReportRequest $request): JsonResponse|StreamedResponse
    {
        $filters = $request->validated();
        $data = $this->referralService->getReferralReport($filters);

        if ($request->query('export') === 'excel') {
            return $this->referralService->exportExcel($data);
        }
        if ($request->query('export') === 'csv') {
            return $this->referralService->exportCsv($data);
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Wallet Statement
     */
    #[OA\Get(
        path: '/admin/reports/wallet-statement',
        summary: 'Admin — Wallet Statement Report',
        description: 'Returns wallet statement opening/closing balances, total credits, debits, and transaction list.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Reports'],
        parameters: [
            new OA\Parameter(name: 'wallet_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'user_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'start_date', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'end_date', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'export', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['csv', 'excel'])),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Wallet statement retrieved successfully.'),
        ]
    )]
    public function walletStatement(WalletReportRequest $request): JsonResponse|StreamedResponse
    {
        $filters = $request->validated();
        $data = $this->walletReportService->getWalletStatement($filters);

        if ($request->query('export') === 'excel') {
            return $this->walletReportService->exportStatementExcel($data);
        }
        if ($request->query('export') === 'csv') {
            return $this->walletReportService->exportStatementCsv($data);
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Credit/Debit History
     */
    #[OA\Get(
        path: '/admin/reports/wallet-credit-debit',
        summary: 'Admin — Credit/Debit History Report',
        description: 'Returns granular list of wallet credit and debit transactions.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Reports'],
        parameters: [
            new OA\Parameter(name: 'wallet_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'user_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'type', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['credit', 'debit'])),
            new OA\Parameter(name: 'start_date', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'end_date', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'export', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['csv', 'excel'])),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Credit/Debit history report retrieved successfully.'),
        ]
    )]
    public function creditDebitHistory(WalletReportRequest $request): JsonResponse|StreamedResponse
    {
        $filters = $request->validated();
        $data = $this->walletReportService->getCreditDebitHistory($filters);

        if ($request->query('export') === 'excel') {
            return $this->walletReportService->exportHistoryExcel($data);
        }
        if ($request->query('export') === 'csv') {
            return $this->walletReportService->exportHistoryCsv($data);
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Cashout Report
     */
    #[OA\Get(
        path: '/admin/reports/cashouts',
        summary: 'Admin — Cashout Report',
        description: 'Returns withdrawal/cashout request records and status filters.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Reports'],
        parameters: [
            new OA\Parameter(name: 'driver_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['pending', 'approved', 'rejected', 'completed'])),
            new OA\Parameter(name: 'start_date', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'end_date', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'export', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['csv', 'excel'])),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Cashout report retrieved successfully.'),
        ]
    )]
    public function cashoutReport(CashoutReportRequest $request): JsonResponse|StreamedResponse
    {
        $filters = $request->validated();
        $data = $this->walletReportService->getCashoutReport($filters);

        if ($request->query('export') === 'excel') {
            return $this->walletReportService->exportCashoutExcel($data);
        }
        if ($request->query('export') === 'csv') {
            return $this->walletReportService->exportCashoutCsv($data);
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Ledger Report
     */
    #[OA\Get(
        path: '/admin/reports/ledger',
        summary: 'Admin — Double-Entry Ledger Report',
        description: 'Returns audit ledger records for double-entry financial accounting.',
        security: [['bearerAuth' => []]],
        tags: ['Admin Reports'],
        parameters: [
            new OA\Parameter(name: 'user_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'transaction_type', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'reference', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'start_date', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'end_date', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'export', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['csv', 'excel'])),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Ledger report retrieved successfully.'),
        ]
    )]
    public function ledgerReport(LedgerReportRequest $request): JsonResponse|StreamedResponse
    {
        $filters = $request->validated();
        $data = $this->walletReportService->getLedgerReport($filters);

        if ($request->query('export') === 'excel') {
            return $this->walletReportService->exportLedgerExcel($data);
        }
        if ($request->query('export') === 'csv') {
            return $this->walletReportService->exportLedgerCsv($data);
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}
