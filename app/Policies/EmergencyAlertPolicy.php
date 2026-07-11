<?php

namespace App\Policies;

use App\Models\EmergencyAlert;
use App\Models\User;

class EmergencyAlertPolicy
{
    /**
     * Determine whether the user can view the alert.
     */
    public function view(User $user, EmergencyAlert $alert): bool
    {
        if ($user->role->value === 'admin') {
            return true;
        }

        $isRider = (int) $user->id === (int) $alert->user_id;
        $isDriver = (int) $user->id === (int) $alert->driver_id;

        return $isRider || $isDriver;
    }

    /**
     * Determine whether the user can acknowledge the alert (Drivers only).
     */
    public function acknowledge(User $user, EmergencyAlert $alert): bool
    {
        return $user->role->value === 'driver' && (int) $user->id === (int) $alert->driver_id;
    }

    /**
     * Determine whether the user can assign an admin to the alert (Admins only).
     */
    public function assign(User $user, EmergencyAlert $alert): bool
    {
        return $user->role->value === 'admin';
    }

    /**
     * Determine whether the user can resolve the alert.
     */
    public function resolve(User $user, EmergencyAlert $alert): bool
    {
        if ($user->role->value === 'admin') {
            return true;
        }

        // Rider who triggered it can resolve it
        return (int) $user->id === (int) $alert->user_id;
    }

    /**
     * Determine whether the user can close the alert (Admins only).
     */
    public function close(User $user, EmergencyAlert $alert): bool
    {
        return $user->role->value === 'admin';
    }
}
