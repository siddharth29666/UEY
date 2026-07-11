<?php

namespace App\Services;

use App\Events\EmergencyAcknowledged;
use App\Events\EmergencyAssigned;
use App\Events\EmergencyClosed;
use App\Events\EmergencyResolved;
use App\Events\EmergencyTriggered;
use App\Models\EmergencyAlert;
use App\Models\Ride;
use App\Models\User;
use App\Notifications\EmergencyAcknowledgedNotification;
use App\Notifications\EmergencyAssignedNotification;
use App\Notifications\EmergencyResolvedNotification;
use App\Notifications\EmergencyTriggeredNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class EmergencyService
{
    public function __construct(
        protected AuditLogService $auditService
    ) {}

    /**
     * Trigger SOS alert during active ride.
     */
    public function triggerSOS(
        Ride $ride,
        User $rider,
        float $latitude,
        float $longitude,
        ?string $message = null,
        array $files = []
    ): EmergencyAlert {
        // 1. Validate ride is active
        $activeStatuses = ['accepted', 'arriving', 'arrived', 'started'];
        if (! in_array($ride->status->value, $activeStatuses)) {
            throw ValidationException::withMessages([
                'ride' => ['SOS can only be triggered during an active ride.'],
            ]);
        }

        // 2. Check active SOS conflict (409 Conflict)
        $hasActiveSOS = EmergencyAlert::where('ride_id', $ride->id)
            ->whereIn('status', ['active', 'acknowledged', 'assigned'])
            ->exists();
        if ($hasActiveSOS) {
            throw new ConflictHttpException('An active SOS alert already exists for this ride.');
        }

        // 3. Store optional attachments
        $attachmentPath = null;
        $attachmentType = null;

        if (! empty($files['photo'])) {
            $attachmentPath = $files['photo']->store('emergency_attachments', 'public');
            $attachmentType = 'photo';
        } elseif (! empty($files['audio'])) {
            $attachmentPath = $files['audio']->store('emergency_attachments', 'public');
            $attachmentType = 'audio';
        } elseif (! empty($files['video'])) {
            $attachmentPath = $files['video']->store('emergency_attachments', 'public');
            $attachmentType = 'video';
        }

        return DB::transaction(function () use ($ride, $rider, $latitude, $longitude, $message, $attachmentPath, $attachmentType) {
            $driverUser = $ride->driverProfile ? $ride->driverProfile->user : null;

            // Store Database Alert
            $alert = EmergencyAlert::create([
                'ride_id' => $ride->id,
                'user_id' => $rider->id,
                'driver_id' => $driverUser ? $driverUser->id : null,
                'status' => 'active',
                'latitude' => $latitude,
                'longitude' => $longitude,
                'message' => $message,
                'attachment' => $attachmentPath,
                'attachment_type' => $attachmentType,
            ]);

            // Add Timeline Log
            $alert->histories()->create([
                'status' => 'active',
                'message' => 'Emergency SOS triggered by rider.',
                'created_by' => $rider->id,
            ]);

            // Dispatch Broadcast
            event(new EmergencyTriggered($alert));

            // Send Notifications
            $rider->notify(new EmergencyTriggeredNotification($alert));
            if ($driverUser) {
                $driverUser->notify(new EmergencyTriggeredNotification($alert));
            }

            $admins = User::where('role', 'admin')->get();
            foreach ($admins as $admin) {
                $admin->notify(new EmergencyTriggeredNotification($alert));
            }

            // Create Audit Log
            $auditAdmin = $this->getAdminForAudit();
            $this->auditService->log(
                $auditAdmin,
                'emergency',
                'sos_triggered',
                'emergency_alerts',
                $alert->id,
                null,
                $alert->toArray()
            );

            return $alert;
        });
    }

    /**
     * Driver Acknowledge SOS alert.
     */
    public function acknowledgeSOS(EmergencyAlert $alert, User $driver): void
    {
        if ($alert->status !== 'active') {
            throw new \Exception('SOS alert is not in active status and cannot be acknowledged.');
        }

        DB::transaction(function () use ($alert, $driver) {
            $alert->update(['status' => 'acknowledged']);

            // Write Timeline
            $alert->histories()->create([
                'status' => 'acknowledged',
                'message' => "SOS Alert acknowledged by driver {$driver->name}.",
                'created_by' => $driver->id,
            ]);

            // Dispatch Broadcast
            event(new EmergencyAcknowledged($alert));

            // Send Notifications
            $alert->user->notify(new EmergencyAcknowledgedNotification($alert));
            $driver->notify(new EmergencyAcknowledgedNotification($alert));

            $admins = User::where('role', 'admin')->get();
            foreach ($admins as $admin) {
                $admin->notify(new EmergencyAcknowledgedNotification($alert));
            }

            // Create Audit Log
            $auditAdmin = $this->getAdminForAudit();
            $this->auditService->log(
                $auditAdmin,
                'emergency',
                'sos_acknowledged',
                'emergency_alerts',
                $alert->id,
                ['status' => 'active'],
                ['status' => 'acknowledged']
            );
        });
    }

    /**
     * Admin Assign SOS alert.
     */
    public function assignAdmin(EmergencyAlert $alert, User $admin): void
    {
        DB::transaction(function () use ($alert, $admin) {
            $oldStatus = $alert->status;
            $oldResolver = $alert->resolved_by;

            $alert->update([
                'status' => 'assigned',
                'resolved_by' => $admin->id,
            ]);

            // Write Timeline
            $alert->histories()->create([
                'status' => 'assigned',
                'message' => "SOS Alert assigned to administrator {$admin->name}.",
                'created_by' => $admin->id,
            ]);

            // Dispatch Broadcast
            event(new EmergencyAssigned($alert));

            // Send Notifications
            $alert->user->notify(new EmergencyAssignedNotification($alert));
            if ($alert->driver) {
                $alert->driver->notify(new EmergencyAssignedNotification($alert));
            }

            $admins = User::where('role', 'admin')->get();
            foreach ($admins as $ad) {
                $ad->notify(new EmergencyAssignedNotification($alert));
            }

            // Create Audit Log
            $this->auditService->log(
                $admin,
                'emergency',
                'sos_assigned',
                'emergency_alerts',
                $alert->id,
                ['status' => $oldStatus, 'resolved_by' => $oldResolver],
                ['status' => 'assigned', 'resolved_by' => $admin->id]
            );
        });
    }

    /**
     * Resolve SOS alert.
     */
    public function resolveSOS(EmergencyAlert $alert, User $resolver, ?string $adminNote = null): void
    {
        DB::transaction(function () use ($alert, $resolver, $adminNote) {
            $oldStatus = $alert->status;

            $alert->update([
                'status' => 'resolved',
                'resolved_by' => $resolver->id,
                'resolved_at' => now(),
            ]);

            // Write Timeline
            $alert->histories()->create([
                'status' => 'resolved',
                'message' => $adminNote ?: 'SOS Alert resolved.',
                'created_by' => $resolver->id,
            ]);

            // Dispatch Broadcast
            event(new EmergencyResolved($alert));

            // Send Notifications
            $alert->user->notify(new EmergencyResolvedNotification($alert));
            if ($alert->driver) {
                $alert->driver->notify(new EmergencyResolvedNotification($alert));
            }

            $admins = User::where('role', 'admin')->get();
            foreach ($admins as $ad) {
                $ad->notify(new EmergencyResolvedNotification($alert));
            }

            // Create Audit Log
            $auditAdmin = $resolver->role->value === 'admin' ? $resolver : $this->getAdminForAudit();
            $this->auditService->log(
                $auditAdmin,
                'emergency',
                'sos_resolved',
                'emergency_alerts',
                $alert->id,
                ['status' => $oldStatus],
                ['status' => 'resolved', 'resolved_by' => $resolver->id, 'resolved_at' => now()]
            );
        });
    }

    /**
     * Close SOS alert (Admin Only).
     */
    public function closeSOS(EmergencyAlert $alert, User $admin): void
    {
        DB::transaction(function () use ($alert, $admin) {
            $oldStatus = $alert->status;

            $alert->update(['status' => 'closed']);

            // Write Timeline
            $alert->histories()->create([
                'status' => 'closed',
                'message' => 'SOS Alert closed.',
                'created_by' => $admin->id,
            ]);

            // Dispatch Broadcast
            event(new EmergencyClosed($alert));

            // Create Audit Log
            $this->auditService->log(
                $admin,
                'emergency',
                'sos_closed',
                'emergency_alerts',
                $alert->id,
                ['status' => $oldStatus],
                ['status' => 'closed']
            );
        });
    }

    /**
     * Helper to resolve an administrative user for audit trailing.
     */
    protected function getAdminForAudit(): User
    {
        $admin = User::where('role', 'admin')->first();
        if (! $admin) {
            $admin = User::firstOrCreate(
                ['email' => 'system_admin@uey.com'],
                [
                    'name' => 'System Auto Audit',
                    'phone' => '+1000000000',
                    'role' => 'admin',
                    'status' => 'active',
                    'password' => bcrypt('password123'),
                ]
            );
        }

        return $admin;
    }
}
