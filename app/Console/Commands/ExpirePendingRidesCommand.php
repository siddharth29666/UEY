<?php

namespace App\Console\Commands;

use App\Enums\RideStatus;
use App\Models\Ride;
use App\Models\Setting;
use App\Models\User;
use App\Services\RideService;
use App\Services\SchedulerService;
use Illuminate\Console\Command;

class ExpirePendingRidesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:expire-pending-rides';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically cancel pending rides that have timed out without driver acceptance';

    /**
     * Execute the console command.
     */
    public function handle(SchedulerService $schedulerService, RideService $rideService)
    {
        $message = $schedulerService->runCommand($this->signature, function () use ($rideService) {
            $timeoutMinutes = (int) Setting::where('key', 'ride_timeout_minutes')->value('value') ?: 10;
            $threshold = now()->subMinutes($timeoutMinutes);

            $rides = Ride::where('status', RideStatus::PENDING)
                ->where('created_at', '<', $threshold)
                ->get();

            $systemUser = User::where('role', 'admin')->first();
            if (! $systemUser) {
                $systemUser = User::create([
                    'name' => 'System Agent',
                    'email' => 'system.agent@uey.test',
                    'phone' => '+447999999998',
                    'password' => bcrypt('password'),
                    'role' => 'admin',
                    'status' => 'active',
                ]);
            }

            $count = 0;
            foreach ($rides as $ride) {
                $ride->refresh();
                if ($ride->status === RideStatus::PENDING) {
                    $rideService->cancelRide($ride, $systemUser, 'Ride request timed out.');
                    $count++;
                }
            }

            return "Expired {$count} pending rides.";
        });

        $this->info($message);
    }
}
