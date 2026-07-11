<?php

namespace App\Jobs;

use App\Models\Referral;
use App\Services\ReferralService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessReferralBonusJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Referral $referral
    ) {
        $this->onQueue('default');
    }

    /**
     * Execute the job.
     */
    public function handle(ReferralService $referralService): void
    {
        try {
            $referralService->issueReferralBonuses($this->referral);
        } catch (\Exception $e) {
            Log::error('ProcessReferralBonusJob failed for referral #'.$this->referral->id.': '.$e->getMessage());
            throw $e;
        }
    }
}
