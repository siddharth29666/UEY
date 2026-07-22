<?php

namespace App\Console\Commands;

use App\Services\DriverSubscriptionService;
use App\Services\SchedulerService;
use Illuminate\Console\Command;

class ExpireSubscriptionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:expire-subscriptions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Expire active driver subscriptions where expires_at date has passed';

    /**
     * Execute the console command.
     */
    public function handle(SchedulerService $schedulerService, DriverSubscriptionService $subscriptionService): void
    {
        $message = $schedulerService->runCommand($this->signature, function () use ($subscriptionService) {
            $expiredCount = $subscriptionService->expireSubscriptions();

            return "Subscription expiration task completed. Expired {$expiredCount} subscriptions.";
        });

        $this->info($message);
    }
}
