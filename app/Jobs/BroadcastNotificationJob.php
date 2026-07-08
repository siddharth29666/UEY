<?php

namespace App\Jobs;

use App\Enums\NotificationType;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class BroadcastNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected string $target,
        protected array $userIds,
        protected string $title,
        protected string $body,
        protected string $category,
        protected string $priority
    ) {
        $this->onQueue('notifications');
    }

    /**
     * Execute the job.
     */
    public function handle(NotificationService $notificationService): void
    {
        $query = User::query();

        switch ($this->target) {
            case 'all_users':
                $query->whereIn('role', ['rider', 'driver']);
                break;
            case 'all_riders':
                $query->where('role', 'rider');
                break;
            case 'all_drivers':
                $query->where('role', 'driver');
                break;
            case 'selected_users':
            case 'selected_riders':
            case 'selected_drivers':
                $query->whereIn('id', $this->userIds);
                break;
        }

        $type = match ($this->category) {
            'promotion' => NotificationType::PROMOTION,
            'system' => NotificationType::SYSTEM,
            default => NotificationType::ADMIN_NOTIFICATION,
        };

        $query->chunk(100, function ($users) use ($notificationService, $type) {
            foreach ($users as $user) {
                $notificationService->sendToUser(
                    $user,
                    $type,
                    $this->title,
                    $this->body
                );
            }
        });
    }
}
