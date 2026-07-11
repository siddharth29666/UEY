<?php

namespace App\Console\Commands;

use App\Models\OtpVerification;
use App\Models\Setting;
use App\Services\SchedulerService;
use Illuminate\Console\Command;

class CleanupOtpCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:cleanup-otp';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cleanup expired OTP verification records from database';

    /**
     * Execute the console command.
     */
    public function handle(SchedulerService $schedulerService)
    {
        $message = $schedulerService->runCommand($this->signature, function () {
            $expiryMinutes = (int) Setting::where('key', 'otp_expiry_minutes')->value('value') ?: 5;
            $threshold = now()->subMinutes($expiryMinutes);

            $deleted = OtpVerification::where('expires_at', '<', now())
                ->orWhere('created_at', '<', $threshold)
                ->delete();

            return "Cleaned up {$deleted} expired OTP verification records.";
        });

        $this->info($message);
    }
}
