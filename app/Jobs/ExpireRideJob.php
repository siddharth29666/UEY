<?php

namespace App\Jobs;

use App\Enums\RideStatus;
use App\Models\Ride;
use App\Models\User;
use App\Services\RideService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ExpireRideJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Ride $ride
    ) {
        $this->onQueue('default');
    }

    /**
     * Execute the job.
     */
    public function handle(RideService $rideService): void
    {
        $this->ride->refresh();

        if ($this->ride->status !== RideStatus::PENDING) {
            return;
        }

        try {
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

            $rideService->cancelRide($this->ride, $systemUser, 'Ride request timed out.');
        } catch (\Exception $e) {
            Log::error('ExpireRideJob failed for ride #'.$this->ride->id.': '.$e->getMessage());
            throw $e;
        }
    }
}
