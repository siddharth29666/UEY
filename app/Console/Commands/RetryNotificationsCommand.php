<?php

namespace App\Console\Commands;

use App\Enums\NotificationStatus;
use App\Jobs\RetryNotificationJob;
use App\Models\NotificationLog;
use App\Services\SchedulerService;
use Illuminate\Console\Command;

class RetryNotificationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:retry-notifications';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically retry delivery of failed notifications';

    /**
     * Execute the console command.
     */
    public function handle(SchedulerService $schedulerService)
    {
        $message = $schedulerService->runCommand($this->signature, function () {
            $failedLogs = NotificationLog::where('status', NotificationStatus::FAILED)
                ->where('created_at', '>', now()->subDays(3))
                ->get();

            $count = 0;
            foreach ($failedLogs as $log) {
                // Dispatch retry job (on notifications queue as per Rule 1)
                RetryNotificationJob::dispatch($log)->onQueue('notifications');
                $count++;
            }

            return "Dispatched retry jobs for {$count} failed notifications.";
        });

        $this->info($message);
    }
}
