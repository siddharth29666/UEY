<?php

namespace App\Console\Commands;

use App\Models\WalletTransaction;
use App\Services\LedgerService;
use Illuminate\Console\Command;

class LedgerBackfillCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:ledger-backfill';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill missing ledger entries for all historical wallet transactions (idempotent)';

    /**
     * Execute the console command.
     */
    public function handle(LedgerService $ledgerService): int
    {
        $this->info('Starting ledger backfill...');

        $total = 0;
        $created = 0;
        $skipped = 0;

        // Chunk to avoid memory exhaustion on large datasets.
        WalletTransaction::with('wallet.user')
            ->orderBy('id')
            ->chunk(200, function ($transactions) use ($ledgerService, &$total, &$created, &$skipped) {
                foreach ($transactions as $tx) {
                    $total++;
                    $result = $ledgerService->createIfMissing($tx);

                    if ($result !== null) {
                        $created++;
                    } else {
                        $skipped++;
                    }
                }
            });

        $this->info('Backfill complete.');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total transactions scanned', $total],
                ['Ledger entries created',     $created],
                ['Already existed (skipped)',  $skipped],
            ]
        );

        return self::SUCCESS;
    }
}
