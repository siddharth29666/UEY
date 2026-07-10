<?php

namespace App\Listeners;

use App\Events\NotificationEvent;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendPaymentNotification implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'notifications';

    public int $tries = 3;

    public int $timeout = 120;

    public array $backoff = [60, 120, 300];

    public function __construct(
        protected NotificationService $notificationService
    ) {}

    /**
     * Handle the event.
     */
    public function handle(NotificationEvent $event): void
    {
        try {
            $this->notificationService->sendToUser(
                $event->user,
                $event->type,
                $event->title,
                $event->body,
                $event->data
            );
        } catch (\Exception $e) {
            Log::error('SendPaymentNotification failed: '.$e->getMessage());
            throw $e;
        }
    }
}
