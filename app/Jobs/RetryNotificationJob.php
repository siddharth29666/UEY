<?php

namespace App\Jobs;

use App\Enums\NotificationStatus;
use App\Models\NotificationLog;
use App\Services\FirebaseService;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RetryNotificationJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public NotificationLog $log
    ) {
        $this->onQueue('notifications');
    }

    /**
     * Execute the job.
     */
    public function handle(NotificationService $notificationService, FirebaseService $firebaseService): void
    {
        $logRecord = $this->log;
        $user = $logRecord->user;

        if (! $user) {
            $logRecord->update([
                'status' => NotificationStatus::FAILED,
                'failure_reason' => 'User associated with notification not found.',
            ]);

            return;
        }

        try {
            $devices = $user->devices;
            if ($devices->isNotEmpty()) {
                $tokens = $devices->pluck('device_token')->toArray();
                $results = $firebaseService->sendMultiple(
                    $tokens,
                    $logRecord->title,
                    $logRecord->body,
                    $logRecord->payload ?: []
                );

                $sentCount = 0;
                $failedReasons = [];
                $firstMessageId = null;

                foreach ($results as $token => $res) {
                    if ($res['success'] ?? false) {
                        $sentCount++;
                        $firstMessageId = $res['message_id'] ?? null;
                    } else {
                        $failedReasons[] = 'Token: '.substr($token, 0, 10).'... Error: '.($res['error'] ?? 'Unknown');
                    }
                }

                if ($sentCount > 0) {
                    $logRecord->update([
                        'status' => NotificationStatus::SENT,
                        'firebase_message_id' => $firstMessageId,
                        'sent_at' => now(),
                    ]);
                } else {
                    $logRecord->update([
                        'status' => NotificationStatus::FAILED,
                        'failure_reason' => 'Retry failed: '.implode(' | ', $failedReasons),
                    ]);
                    throw new \Exception('Firebase failed to deliver to all tokens.');
                }
            } else {
                $logRecord->update([
                    'status' => NotificationStatus::SENT,
                    'sent_at' => now(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('RetryNotificationJob failed for log #'.$logRecord->id.': '.$e->getMessage());
            throw $e;
        }
    }
}
