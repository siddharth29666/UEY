<?php

namespace App\Services;

use App\Enums\NotificationCategory;
use App\Enums\NotificationStatus;
use App\Enums\NotificationType;
use App\Models\NotificationLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class NotificationService
{
    public function __construct(
        protected FirebaseService $firebaseService
    ) {}

    /**
     * Send localized notification to a single user.
     */
    public function sendToUser(
        User $user,
        NotificationType $type,
        ?string $title = null,
        ?string $body = null,
        array $data = []
    ): NotificationLog {
        return DB::transaction(function () use ($user, $type, $title, $body, $data) {
            $category = $type->category();
            $priority = $type->priority();

            // Translate message parameters
            $translationKey = $this->getTranslationKey($type);
            $resolvedTitle = $title ?: $this->getDefaultTitle($category);
            $resolvedBody = $body ?: __($translationKey, $data);

            // Append badge count
            $badge = $this->unreadCount($user) + 1;
            $data['badge'] = $badge;

            // 1. Create audit log
            $log = NotificationLog::create([
                'user_id' => $user->id,
                'title' => $resolvedTitle,
                'body' => $resolvedBody,
                'type' => $type,
                'category' => $category,
                'priority' => $priority,
                'payload' => $data,
                'status' => NotificationStatus::PENDING,
            ]);

            // 2. Validate preferences
            $shouldPush = $this->shouldPushNotification($user, $category);

            if ($shouldPush) {
                $devices = $user->devices;
                if ($devices->isNotEmpty()) {
                    $tokens = $devices->pluck('device_token')->toArray();
                    $results = $this->firebaseService->sendMultiple($tokens, $resolvedTitle, $resolvedBody, $data);

                    $sentCount = 0;
                    $failedReasons = [];
                    $firstMessageId = null;

                    foreach ($results as $token => $res) {
                        if ($res['success'] ?? false) {
                            $sentCount++;
                            $firstMessageId = $res['message_id'] ?? null;
                        } else {
                            $failedReasons[] = "Token: " . substr($token, 0, 10) . "... Error: " . ($res['error'] ?? 'Unknown');
                        }
                    }

                    if ($sentCount > 0) {
                        $log->update([
                            'status' => NotificationStatus::SENT,
                            'firebase_message_id' => $firstMessageId,
                            'sent_at' => now(),
                        ]);
                    } else {
                        $log->update([
                            'status' => NotificationStatus::FAILED,
                            'failure_reason' => implode(' | ', $failedReasons),
                        ]);
                    }
                } else {
                    // No devices registered, marked as sent in DB
                    $log->update([
                        'status' => NotificationStatus::SENT,
                        'sent_at' => now(),
                    ]);
                }
            } else {
                // Preferences disabled push: DB only
                $log->update([
                    'status' => NotificationStatus::SENT,
                    'sent_at' => now(),
                ]);
            }

            return $log;
        });
    }

    /**
     * Send notifications to multiple users.
     */
    public function sendToUsers(
        array $users,
        NotificationType $type,
        ?string $title = null,
        ?string $body = null,
        array $data = []
    ): array {
        $logs = [];
        foreach ($users as $user) {
            $logs[] = $this->sendToUser($user, $type, $title, $body, $data);
        }
        return $logs;
    }

    /**
     * Send push notification to a topic.
     */
    public function sendToTopic(
        string $topic,
        NotificationType $type,
        ?string $title = null,
        ?string $body = null,
        array $data = []
    ): array {
        $category = $type->category();
        $resolvedTitle = $title ?: $this->getDefaultTitle($category);
        $resolvedBody = $body ?: __($this->getTranslationKey($type), $data);

        return $this->firebaseService->sendToTopic($topic, $resolvedTitle, $resolvedBody, $data);
    }

    /**
     * Mark a single notification log as read.
     */
    public function markAsRead(NotificationLog $notification): void
    {
        $notification->update([
            'status' => NotificationStatus::READ,
            'read_at' => now(),
        ]);
    }

    /**
     * Mark all notifications of a user as read.
     */
    public function markAllAsRead(User $user): void
    {
        NotificationLog::where('user_id', $user->id)
            ->whereNull('read_at')
            ->update([
                'status' => NotificationStatus::READ,
                'read_at' => now(),
            ]);
    }

    /**
     * Delete a notification log.
     */
    public function delete(NotificationLog $notification): void
    {
        $notification->delete();
    }

    /**
     * Restore a soft-deleted notification log.
     */
    public function restore(int $id, User $user): ?NotificationLog
    {
        $log = NotificationLog::onlyTrashed()
            ->where('user_id', $user->id)
            ->where('id', $id)
            ->first();

        if ($log) {
            $log->restore();
            return $log;
        }

        return null;
    }

    /**
     * Get unread notifications count for a user.
     */
    public function unreadCount(User $user): int
    {
        return NotificationLog::where('user_id', $user->id)
            ->whereNull('read_at')
            ->count();
    }

    /**
     * Determine if a push notification should be sent based on preferences.
     */
    protected function shouldPushNotification(User $user, NotificationCategory $category): bool
    {
        // Check database user notification preferences (firstOrCreate defaults to true)
        $pref = $user->notificationPreference()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'ride_notifications' => true,
                'wallet_notifications' => true,
                'payment_notifications' => true,
                'promotion_notifications' => true,
                'system_notifications' => true,
            ]
        );

        return match ($category) {
            NotificationCategory::RIDE => $pref->ride_notifications,
            NotificationCategory::WALLET => $pref->wallet_notifications,
            NotificationCategory::PAYMENT => $pref->payment_notifications,
            NotificationCategory::PROMOTION => $pref->promotion_notifications,
            NotificationCategory::SYSTEM => $pref->system_notifications,
            // Administrative, Reviews, and Driver document updates always push
            default => true,
        };
    }

    /**
     * Get English translation key for NotificationType.
     */
    protected function getTranslationKey(NotificationType $type): string
    {
        return match ($type) {
            NotificationType::RIDE_REQUESTED => 'notifications.ride.requested',
            NotificationType::RIDE_ACCEPTED => 'notifications.ride.accepted',
            NotificationType::DRIVER_ARRIVING => 'notifications.ride.arriving',
            NotificationType::DRIVER_ARRIVED => 'notifications.ride.arrived',
            NotificationType::RIDE_STARTED => 'notifications.ride.started',
            NotificationType::RIDE_COMPLETED => 'notifications.ride.completed',
            NotificationType::RIDE_CANCELLED => 'notifications.ride.cancelled',

            NotificationType::WALLET_TOPUP => 'notifications.wallet.topup',
            NotificationType::WALLET_CREDIT => 'notifications.wallet.credit',
            NotificationType::WALLET_DEBIT => 'notifications.wallet.debit',
            NotificationType::WITHDRAW_REQUESTED => 'notifications.wallet.withdraw_requested',
            NotificationType::WITHDRAW_APPROVED => 'notifications.wallet.withdraw_approved',
            NotificationType::WITHDRAW_REJECTED => 'notifications.wallet.withdraw_rejected',
            NotificationType::WITHDRAW_COMPLETED => 'notifications.wallet.withdraw_completed',

            NotificationType::PAYMENT_SUCCESS => 'notifications.payment.success',
            NotificationType::PAYMENT_FAILED => 'notifications.payment.failed',

            NotificationType::REVIEW_RECEIVED => 'notifications.review.received',

            NotificationType::DRIVER_DOCUMENT_APPROVED => 'notifications.driver.document_approved',
            NotificationType::DRIVER_DOCUMENT_REJECTED => 'notifications.driver.document_rejected',

            NotificationType::ADMIN_NOTIFICATION => 'notifications.admin.broadcast',
            NotificationType::PROMOTION => 'notifications.promotion',
            NotificationType::COUPON => 'notifications.coupon',
            NotificationType::SYSTEM => 'notifications.system',
            NotificationType::REFERRAL_BONUS => 'notifications.referral_bonus',
        };
    }

    /**
     * Get default title for notification category.
     */
    protected function getDefaultTitle(NotificationCategory $category): string
    {
        return match ($category) {
            NotificationCategory::RIDE => 'Ride Update',
            NotificationCategory::WALLET => 'Wallet Balance Alert',
            NotificationCategory::PAYMENT => 'Payment Confirmation',
            NotificationCategory::REVIEW => 'New Review Submitted',
            NotificationCategory::DRIVER => 'Driver Account Notice',
            NotificationCategory::ADMIN => 'System Announcement',
            NotificationCategory::PROMOTION => 'Special Promotion For You',
            NotificationCategory::SYSTEM => 'System Update',
        };
    }
}
