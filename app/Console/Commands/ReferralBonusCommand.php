<?php

namespace App\Console\Commands;

use App\Models\Referral;
use App\Services\ReferralService;
use App\Services\SchedulerService;
use Illuminate\Console\Command;

class ReferralBonusCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:referral-bonus';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process and credit pending referral rewards for completed first rides';

    /**
     * Execute the console command.
     */
    public function handle(SchedulerService $schedulerService, ReferralService $referralService)
    {
        $message = $schedulerService->runCommand($this->signature, function () use ($referralService) {
            $referrals = Referral::where('status', 'completed')
                ->whereNull('rewarded_at')
                ->get();

            $count = 0;
            foreach ($referrals as $referral) {
                $referral->refresh();
                if ($referral->status === 'completed' && $referral->rewarded_at === null) {
                    $referralService->issueReferralBonuses($referral);
                    $count++;
                }
            }

            return "Processed referral rewards for {$count} referrals.";
        });

        $this->info($message);
    }
}
