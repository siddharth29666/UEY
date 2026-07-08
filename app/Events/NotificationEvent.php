<?php

namespace App\Events;

use App\Enums\NotificationCategory;
use App\Enums\NotificationPriority;
use App\Enums\NotificationType;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

abstract class NotificationEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public User $user,
        public NotificationType $type,
        public ?string $title = null,
        public ?string $body = null,
        public array $data = []
    ) {}

    /**
     * Get the category of the notification.
     */
    public function category(): NotificationCategory
    {
        return $this->type->category();
    }

    /**
     * Get the priority of the notification.
     */
    public function priority(): NotificationPriority
    {
        return $this->type->priority();
    }
}
