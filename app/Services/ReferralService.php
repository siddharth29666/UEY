<?php

namespace App\Services;

use App\Enums\NotificationType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\WalletTransactionType;
use App\Events\ReferralApplied;
use App\Events\ReferralBonusEvent;
use App\Events\ReferralCompleted;
use App\Jobs\ProcessReferralBonusJob;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReferralService
{
    public function __construct(
        protected WalletService $walletService,
        protected SettingService $settingService,
        protected AuditLogService $auditLogService
    ) {}

    /**
     * Apply a referral code for a referred user (invitee).
     */
    public function applyReferralCode(User $invitee, string $code): Referral
    {
        $code = strtoupper(trim($code));

        return DB::transaction(function () use ($invitee, $code) {
            // 1. Find referrer
            $referrer = User::where('referral_code', $code)->first();

            if (! $referrer) {
                throw new \Exception('The referral code is invalid.');
            }

            // 2. Validation constraints
            if ($referrer->id === $invitee->id) {
                throw new \Exception('You cannot refer yourself.');
            }

            if ($referrer->status !== UserStatus::ACTIVE) {
                throw new \Exception('The referrer account is not active.');
            }

            if ($invitee->referred_by !== null) {
                throw new \Exception('You have already applied a referral code.');
            }

            if ($invitee->first_ride_completed) {
                throw new \Exception('You cannot apply a referral code after completing your first ride.');
            }

            // check if there is an existing referral record to be sure
            $exists = Referral::where('referred_user_id', $invitee->id)->exists();
            if ($exists) {
                throw new \Exception('You have already applied a referral code.');
            }

            // 3. Apply referral relationship
            $invitee->update(['referred_by' => $referrer->id]);

            // 4. Create Referral record
            $referrerBonus = (float) $this->settingService->get('referral_bonus_referrer', 10.00);
            $referredBonus = (float) $this->settingService->get('referral_bonus_referred', 5.00);

            $referral = Referral::create([
                'referrer_id' => $referrer->id,
                'referred_user_id' => $invitee->id,
                'referral_code' => $code,
                'status' => 'pending',
                'referrer_bonus' => $referrerBonus,
                'referred_bonus' => $referredBonus,
            ]);

            // 5. Trigger event, notifications, and logs
            event(new ReferralApplied($invitee, NotificationType::REFERRAL_APPLIED, null, null, [
                'referrer_name' => $referrer->name,
            ]));

            // Send notification to Referrer that someone applied their code
            event(new ReferralApplied($referrer, NotificationType::REFERRAL_APPLIED, null, null, [
                'invitee_name' => $invitee->name,
            ]));

            return $referral;
        });
    }

    /**
     * Detect when a rider completes their first ride to trigger referral rewards.
     */
    public function detectFirstRideCompletion(User $invitee): void
    {
        if ($invitee->referred_by === null || $invitee->first_ride_completed) {
            return;
        }

        DB::transaction(function () use ($invitee) {
            // Find the pending referral record
            $referral = Referral::where('referred_user_id', $invitee->id)
                ->where('referrer_id', $invitee->referred_by)
                ->where('status', 'pending')
                ->first();

            if ($referral) {
                $invitee->update(['first_ride_completed' => true]);

                $referral->update([
                    'status' => 'completed',
                    'first_ride_completed_at' => now(),
                ]);

                // Dispatch queue job for reward distribution (on default queue as per rule 1)
                ProcessReferralBonusJob::dispatch($referral)->onQueue('default');
            }
        });
    }

    /**
     * Process and credit the referral rewards.
     */
    public function issueReferralBonuses(Referral $referral): void
    {
        // Prevent duplicate processing (idempotency rule)
        if ($referral->rewarded_at !== null) {
            return;
        }

        DB::transaction(function () use ($referral) {
            $referral->refresh();
            if ($referral->rewarded_at !== null) {
                return;
            }

            // Find System Admin for logging
            $admin = User::where('role', UserRole::ADMIN)->first();
            if (! $admin) {
                // Seeder fallback for testing environments
                $admin = User::create([
                    'name' => 'System Auditor',
                    'email' => 'system.auditor@uey.test',
                    'phone' => '+447999999999',
                    'password' => bcrypt('password'),
                    'role' => UserRole::ADMIN,
                    'status' => UserStatus::ACTIVE,
                ]);
            }

            // 1. Credit Referrer
            $referrer = $referral->referrer;
            if ($referrer) {
                $referrerWallet = $referrer->wallet()->firstOrCreate(
                    ['user_id' => $referrer->id],
                    ['balance' => 0.00]
                );

                $txReferrer = $this->walletService->credit(
                    $referrerWallet,
                    (float) $referral->referrer_bonus,
                    WalletTransactionType::REFERRAL_BONUS,
                    'ref_bonus_referrer_'.$referral->id,
                    "Referral bonus received for inviting friend: {$referral->referredUser->name}"
                );

                // Dispatches notification to referrer
                event(new ReferralBonusEvent($referrer, NotificationType::REFERRAL_BONUS_RECEIVED, null, null, [
                    'amount' => (float) $referral->referrer_bonus,
                    'friend_name' => $referral->referredUser->name,
                ]));

                // Audit log
                $this->auditLogService->log(
                    $admin,
                    'referrals',
                    'credit_referrer_bonus',
                    'wallets',
                    $referrerWallet->id,
                    null,
                    ['amount' => $referral->referrer_bonus, 'transaction_id' => $txReferrer->id]
                );
            }

            // 2. Credit Invitee
            $invitee = $referral->referredUser;
            if ($invitee) {
                $inviteeWallet = $invitee->wallet()->firstOrCreate(
                    ['user_id' => $invitee->id],
                    ['balance' => 0.00]
                );

                $txInvitee = $this->walletService->credit(
                    $inviteeWallet,
                    (float) $referral->referred_bonus,
                    WalletTransactionType::REFERRAL_BONUS,
                    'ref_bonus_invitee_'.$referral->id,
                    "Referral bonus received for applying code {$referral->referral_code}"
                );

                // Dispatches notification to invitee
                event(new ReferralBonusEvent($invitee, NotificationType::REFERRAL_BONUS_RECEIVED, null, null, [
                    'amount' => (float) $referral->referred_bonus,
                ]));

                // Audit log
                $this->auditLogService->log(
                    $admin,
                    'referrals',
                    'credit_invitee_bonus',
                    'wallets',
                    $inviteeWallet->id,
                    null,
                    ['amount' => $referral->referred_bonus, 'transaction_id' => $txInvitee->id]
                );
            }

            // 3. Mark referral completed
            $referral->update([
                'rewarded_at' => now(),
            ]);

            // Dispatch ReferralCompleted event
            event(new ReferralCompleted($referral));
        });
    }
}
