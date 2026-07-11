<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\EmergencyAlert;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class EmergencyResolvedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public EmergencyAlert $alert)
    {
        $this->queue = 'notifications';
    }

    public function via(object $notifiable): array
    {
        $notificationService = app(NotificationService::class);
        $type = NotificationType::EMERGENCY_RESOLVED;

        $title = 'Emergency SOS Resolved';
        $body = 'SOS Alert has been resolved.';

        $payload = [
            'ride_id' => $this->alert->ride_id,
            'driver_id' => $this->alert->driver_id,
            'rider_id' => $this->alert->user_id,
            'emergency_alert_id' => $this->alert->id,
            'latitude' => (float) $this->alert->latitude,
            'longitude' => (float) $this->alert->longitude,
            'deep_link' => 'uey://sos/'.$this->alert->id,
            'priority' => 'HIGH',
            'timestamp' => now()->timestamp,
        ];

        $notificationService->sendToUser($notifiable, $type, $title, $body, $payload);

        return [];
    }
}
