<?php

namespace App\Services\Reports;

use App\Exports\CsvExportHelper;
use App\Models\Ledger;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\WithdrawalRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WalletReportService
{
    /**
     * Get Wallet Statement Report.
     */
    public function getWalletStatement(array $filters): array
    {
        $startDate = isset($filters['start_date']) ? Carbon::parse($filters['start_date'])->startOfDay() : Carbon::now()->startOfMonth();
        $endDate = isset($filters['end_date']) ? Carbon::parse($filters['end_date'])->endOfDay() : Carbon::now()->endOfDay();

        $query = WalletTransaction::whereBetween('created_at', [$startDate, $endDate]);

        if (!empty($filters['wallet_id'])) {
            $query->where('wallet_id', $filters['wallet_id']);
        } elseif (!empty($filters['user_id'])) {
            $wallet = Wallet::where('user_id', $filters['user_id'])->first();
            if ($wallet) {
                $query->where('wallet_id', $wallet->id);
            }
        }

        $totalCredits = (float) (clone $query)->where('type', 'credit')->where('status', 'completed')->sum('amount');
        $totalDebits = (float) (clone $query)->where('type', 'debit')->where('status', 'completed')->sum('amount');

        $openingBalance = 0.0;
        $closingBalance = (float) Wallet::when(!empty($filters['wallet_id']), function ($q) use ($filters) {
            $q->where('id', $filters['wallet_id']);
        })->when(!empty($filters['user_id']), function ($q) use ($filters) {
            $q->where('user_id', $filters['user_id']);
        })->sum('balance');

        $transactionCount = (clone $query)->count();

        // Detailed Transactions
        $transactions = (clone $query)->with('wallet.user')
            ->orderBy('id', 'desc')
            ->limit(100)
            ->get()
            ->map(function ($t) {
                return [
                    'id' => $t->id,
                    'wallet_id' => $t->wallet_id,
                    'user_id' => $t->wallet?->user_id,
                    'user_name' => $t->wallet?->user?->name,
                    'type' => $t->type,
                    'transaction_type' => $t->transaction_type instanceof \BackedEnum ? $t->transaction_type->value : (string) $t->transaction_type,
                    'amount' => (float) $t->amount,
                    'balance_before' => (float) $t->balance_before,
                    'balance_after' => (float) $t->balance_after,
                    'status' => $t->status instanceof \BackedEnum ? $t->status->value : (string) $t->status,
                    'reference' => $t->reference,
                    'created_at' => $t->created_at?->toIso8601String(),
                ];
            });

        return [
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'summary' => [
                'opening_balance' => round($openingBalance, 2),
                'total_credits' => round($totalCredits, 2),
                'total_debits' => round($totalDebits, 2),
                'closing_balance' => round($closingBalance, 2),
                'transaction_count' => $transactionCount,
            ],
            'transactions' => $transactions,
        ];
    }

    /**
     * Get Credit/Debit History Report.
     */
    public function getCreditDebitHistory(array $filters): array
    {
        $startDate = isset($filters['start_date']) ? Carbon::parse($filters['start_date'])->startOfDay() : Carbon::now()->startOfMonth();
        $endDate = isset($filters['end_date']) ? Carbon::parse($filters['end_date'])->endOfDay() : Carbon::now()->endOfDay();

        $query = WalletTransaction::with('wallet.user')
            ->whereBetween('created_at', [$startDate, $endDate]);

        if (!empty($filters['wallet_id'])) {
            $query->where('wallet_id', $filters['wallet_id']);
        } elseif (!empty($filters['user_id'])) {
            $wallet = Wallet::where('user_id', $filters['user_id'])->first();
            if ($wallet) {
                $query->where('wallet_id', $wallet->id);
            }
        }

        if (!empty($filters['type'])) {
            $query->where('type', strtolower($filters['type']));
        }

        $records = $query->orderBy('id', 'desc')->get()->map(function ($t) {
            return [
                'transaction_id' => $t->id,
                'user_id' => $t->wallet?->user_id,
                'user_name' => $t->wallet?->user?->name,
                'wallet_id' => $t->wallet_id,
                'type' => $t->type,
                'transaction_type' => $t->transaction_type instanceof \BackedEnum ? $t->transaction_type->value : (string) $t->transaction_type,
                'amount' => (float) $t->amount,
                'balance_before' => (float) $t->balance_before,
                'balance_after' => (float) $t->balance_after,
                'status' => $t->status instanceof \BackedEnum ? $t->status->value : (string) $t->status,
                'reference' => $t->reference,
                'remarks' => $t->remarks,
                'created_at' => $t->created_at?->toIso8601String(),
            ];
        });

        return [
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'total_records' => $records->count(),
            'records' => $records,
        ];
    }

    /**
     * Get Cashout Report.
     */
    public function getCashoutReport(array $filters): array
    {
        $startDate = isset($filters['start_date']) ? Carbon::parse($filters['start_date'])->startOfDay() : Carbon::now()->startOfMonth();
        $endDate = isset($filters['end_date']) ? Carbon::parse($filters['end_date'])->endOfDay() : Carbon::now()->endOfDay();

        $query = WithdrawalRequest::with('wallet.user')
            ->whereBetween('created_at', [$startDate, $endDate]);

        if (!empty($filters['driver_id'])) {
            $wallet = Wallet::where('user_id', $filters['driver_id'])->first();
            if ($wallet) {
                $query->where('wallet_id', $wallet->id);
            }
        }

        if (!empty($filters['status'])) {
            $query->where('status', strtolower($filters['status']));
        }

        $records = $query->orderBy('id', 'desc')->get()->map(function ($w) {
            return [
                'withdrawal_id' => $w->id,
                'driver_id' => $w->wallet?->user_id,
                'driver_name' => $w->wallet?->user?->name,
                'wallet_id' => $w->wallet_id,
                'amount' => (float) $w->amount,
                'status' => $w->status instanceof \BackedEnum ? $w->status->value : (string) $w->status,
                'admin_note' => $w->admin_note,
                'requested_at' => $w->created_at?->toIso8601String(),
                'processed_at' => $w->processed_at?->toIso8601String(),
            ];
        });

        return [
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'total_requests' => $records->count(),
            'records' => $records,
        ];
    }

    /**
     * Get Ledger Report.
     */
    public function getLedgerReport(array $filters): array
    {
        $startDate = isset($filters['start_date']) ? Carbon::parse($filters['start_date'])->startOfDay() : Carbon::now()->startOfMonth();
        $endDate = isset($filters['end_date']) ? Carbon::parse($filters['end_date'])->endOfDay() : Carbon::now()->endOfDay();

        $query = Ledger::with('wallet.user')
            ->whereBetween('created_at', [$startDate, $endDate]);

        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (!empty($filters['transaction_type'])) {
            $query->where('transaction_type', $filters['transaction_type']);
        }

        if (!empty($filters['reference'])) {
            $query->where('reference', $filters['reference']);
        }

        $records = $query->orderBy('id', 'desc')->get()->map(function ($l) {
            return [
                'ledger_id' => $l->id,
                'user_id' => $l->user_id,
                'user_name' => $l->wallet?->user?->name,
                'wallet_id' => $l->wallet_id,
                'reference' => $l->reference,
                'transaction_type' => $l->transaction_type,
                'direction' => $l->direction,
                'amount' => (float) $l->amount,
                'currency' => $l->currency,
                'remarks' => $l->remarks,
                'created_at' => $l->created_at?->toIso8601String(),
            ];
        });

        return [
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'total_entries' => $records->count(),
            'records' => $records,
        ];
    }

    /**
     * Export Wallet Report as CSV.
     */
    public function exportStatementCsv(array $reportData): StreamedResponse
    {
        $headers = ['Transaction ID', 'Wallet ID', 'User ID', 'User Name', 'Type', 'Category', 'Amount', 'Balance Before', 'Balance After', 'Status', 'Reference', 'Date'];
        $rows = function () use ($reportData) {
            foreach ($reportData['transactions'] as $row) {
                yield [
                    $row['id'],
                    $row['wallet_id'],
                    $row['user_id'],
                    $row['user_name'],
                    $row['type'],
                    $row['transaction_type'],
                    $row['amount'],
                    $row['balance_before'],
                    $row['balance_after'],
                    $row['status'],
                    $row['reference'],
                    $row['created_at'],
                ];
            }
        };
        return CsvExportHelper::streamCsv('wallet_statement_' . date('Ymd_His') . '.csv', $headers, $rows());
    }

    public function exportHistoryCsv(array $reportData): StreamedResponse
    {
        $headers = ['Transaction ID', 'Wallet ID', 'User ID', 'User Name', 'Type', 'Category', 'Amount', 'Balance Before', 'Balance After', 'Status', 'Reference', 'Remarks', 'Date'];
        $rows = function () use ($reportData) {
            foreach ($reportData['records'] as $row) {
                yield [
                    $row['transaction_id'],
                    $row['wallet_id'],
                    $row['user_id'],
                    $row['user_name'],
                    $row['type'],
                    $row['transaction_type'],
                    $row['amount'],
                    $row['balance_before'],
                    $row['balance_after'],
                    $row['status'],
                    $row['reference'],
                    $row['remarks'],
                    $row['created_at'],
                ];
            }
        };
        return CsvExportHelper::streamCsv('wallet_credit_debit_history_' . date('Ymd_His') . '.csv', $headers, $rows());
    }

    public function exportCashoutCsv(array $reportData): StreamedResponse
    {
        $headers = ['Withdrawal ID', 'Driver ID', 'Driver Name', 'Wallet ID', 'Amount', 'Status', 'Admin Note', 'Requested Date', 'Processed Date'];
        $rows = function () use ($reportData) {
            foreach ($reportData['records'] as $row) {
                yield [
                    $row['withdrawal_id'],
                    $row['driver_id'],
                    $row['driver_name'],
                    $row['wallet_id'],
                    $row['amount'],
                    $row['status'],
                    $row['admin_note'],
                    $row['requested_at'],
                    $row['processed_at'],
                ];
            }
        };
        return CsvExportHelper::streamCsv('cashout_report_' . date('Ymd_His') . '.csv', $headers, $rows());
    }

    public function exportLedgerCsv(array $reportData): StreamedResponse
    {
        $headers = ['Ledger ID', 'User ID', 'User Name', 'Wallet ID', 'Reference', 'Transaction Type', 'Direction', 'Amount', 'Currency', 'Remarks', 'Date'];
        $rows = function () use ($reportData) {
            foreach ($reportData['records'] as $row) {
                yield [
                    $row['ledger_id'],
                    $row['user_id'],
                    $row['user_name'],
                    $row['wallet_id'],
                    $row['reference'],
                    $row['transaction_type'],
                    $row['direction'],
                    $row['amount'],
                    $row['currency'],
                    $row['remarks'],
                    $row['created_at'],
                ];
            }
        };
        return CsvExportHelper::streamCsv('ledger_report_' . date('Ymd_His') . '.csv', $headers, $rows());
    }

    public function exportStatementExcel(array $reportData): StreamedResponse
    {
        $headers = ['Transaction ID', 'Wallet ID', 'User ID', 'User Name', 'Type', 'Category', 'Amount', 'Balance Before', 'Balance After', 'Status', 'Reference', 'Date'];
        $dataRows = [];
        foreach ($reportData['transactions'] as $row) {
            $dataRows[] = [
                $row['id'],
                $row['wallet_id'],
                $row['user_id'],
                $row['user_name'],
                $row['type'],
                $row['transaction_type'],
                $row['amount'],
                $row['balance_before'],
                $row['balance_after'],
                $row['status'],
                $row['reference'],
                $row['created_at'],
            ];
        }
        return \App\Exports\ExcelExportHelper::streamXlsx('wallet_statement', $headers, $dataRows);
    }

    public function exportHistoryExcel(array $reportData): StreamedResponse
    {
        $headers = ['Transaction ID', 'Wallet ID', 'User ID', 'User Name', 'Type', 'Category', 'Amount', 'Balance Before', 'Balance After', 'Status', 'Reference', 'Remarks', 'Date'];
        $dataRows = [];
        foreach ($reportData['records'] as $row) {
            $dataRows[] = [
                $row['transaction_id'],
                $row['wallet_id'],
                $row['user_id'],
                $row['user_name'],
                $row['type'],
                $row['transaction_type'],
                $row['amount'],
                $row['balance_before'],
                $row['balance_after'],
                $row['status'],
                $row['reference'],
                $row['remarks'],
                $row['created_at'],
            ];
        }
        return \App\Exports\ExcelExportHelper::streamXlsx('wallet_credit_debit_history', $headers, $dataRows);
    }

    public function exportCashoutExcel(array $reportData): StreamedResponse
    {
        $headers = ['Withdrawal ID', 'Driver ID', 'Driver Name', 'Wallet ID', 'Amount', 'Status', 'Admin Note', 'Requested Date', 'Processed Date'];
        $dataRows = [];
        foreach ($reportData['records'] as $row) {
            $dataRows[] = [
                $row['withdrawal_id'],
                $row['driver_id'],
                $row['driver_name'],
                $row['wallet_id'],
                $row['amount'],
                $row['status'],
                $row['admin_note'],
                $row['requested_at'],
                $row['processed_at'],
            ];
        }
        return \App\Exports\ExcelExportHelper::streamXlsx('cashout_report', $headers, $dataRows);
    }

    public function exportLedgerExcel(array $reportData): StreamedResponse
    {
        $headers = ['Ledger ID', 'User ID', 'User Name', 'Wallet ID', 'Reference', 'Transaction Type', 'Direction', 'Amount', 'Currency', 'Remarks', 'Date'];
        $dataRows = [];
        foreach ($reportData['records'] as $row) {
            $dataRows[] = [
                $row['ledger_id'],
                $row['user_id'],
                $row['user_name'],
                $row['wallet_id'],
                $row['reference'],
                $row['transaction_type'],
                $row['direction'],
                $row['amount'],
                $row['currency'],
                $row['remarks'],
                $row['created_at'],
            ];
        }
        return \App\Exports\ExcelExportHelper::streamXlsx('ledger_report', $headers, $dataRows);
    }
}
