<?php

namespace App\Console\Commands;

use App\Models\PromoCode;
use App\Services\SchedulerService;
use Illuminate\Console\Command;

class ExpirePromoCodesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:expire-promo-codes';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Deactivate expired promo codes';

    /**
     * Execute the console command.
     */
    public function handle(SchedulerService $schedulerService)
    {
        $message = $schedulerService->runCommand($this->signature, function () {
            $promos = PromoCode::where('is_active', true)
                ->where('expires_at', '<', now())
                ->get();

            $count = 0;
            foreach ($promos as $promo) {
                $promo->update(['is_active' => false]);
                $count++;
            }

            return "Expired {$count} active promo codes.";
        });

        $this->info($message);
    }
}
