<?php

namespace App\Console\Commands;

use App\Models\Wallet;
use App\Services\LedgerService;
use App\Services\SchedulerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class WalletSettlementCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:wallet-settlement';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Perform daily wallet settlement and ledger consistency audits';

    /**
     * Execute the console command.
     */
    public function handle(SchedulerService $schedulerService, LedgerService $ledgerService)
    {
        $message = $schedulerService->runCommand($this->signature, function () use ($ledgerService) {
            $wallets = Wallet::all();
            $inconsistentCount = 0;
            $totalAudited = 0;
            $ledgerCreated = 0;

            foreach ($wallets as $wallet) {
                $txs = $wallet->transactions()->orderBy('created_at', 'asc')->get();

                foreach ($txs as $tx) {
                    $totalAudited++;

                    // --- Balance integrity audit (existing behaviour, unchanged) ---
                    $isCredit = $tx->type === 'credit';
                    $calculatedAfter = $isCredit
                        ? $tx->balance_before + $tx->amount
                        : $tx->balance_before - $tx->amount;

                    if (abs($calculatedAfter - $tx->balance_after) > 0.01) {
                        $inconsistentCount++;
                        Log::warning("Ledger inconsistency found on transaction #{$tx->id} in Wallet #{$wallet->id}. Calculated: {$calculatedAfter}, Stored: {$tx->balance_after}");
                    }

                    // --- Ledger backfill: auto-create missing ledger entry ---
                    $created = $ledgerService->createIfMissing($tx);
                    if ($created !== null) {
                        $ledgerCreated++;
                    }
                }
            }

            return "Wallet settlement ledger audit completed. Audited {$totalAudited} transactions. "
                ."Found {$inconsistentCount} balance inconsistencies. "
                ."Created {$ledgerCreated} missing ledger entries.";
        });

        $this->info($message);
    }
}
