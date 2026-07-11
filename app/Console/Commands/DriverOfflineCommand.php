<?php

namespace App\Console\Commands;

use App\Models\DriverProfile;
use App\Models\Setting;
use App\Services\DriverLocationService;
use App\Services\SchedulerService;
use Illuminate\Console\Command;

class DriverOfflineCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:driver-offline';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically force online drivers offline if they are inactive';

    /**
     * Execute the console command.
     */
    public function handle(SchedulerService $schedulerService, DriverLocationService $locationService)
    {
        $message = $schedulerService->runCommand($this->signature, function () use ($locationService) {
            $offlineMinutes = (int) Setting::where('key', 'driver_offline_minutes')->value('value') ?: 15;
            $threshold = now()->subMinutes($offlineMinutes);

            $drivers = DriverProfile::where('is_online', true)
                ->where(function ($query) use ($threshold) {
                    $query->where('last_seen_at', '<', $threshold)
                        ->orWhereNull('last_seen_at');
                })
                ->get();

            $count = 0;
            foreach ($drivers as $driver) {
                $driver->refresh();
                if ($driver->is_online) {
                    $locationService->toggleOnlineStatus($driver, false);
                    $count++;
                }
            }

            return "Forced {$count} inactive drivers offline.";
        });

        $this->info($message);
    }
}
