<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Ride;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class ConversationService
{
    /**
     * Create or retrieve a conversation thread for a ride.
     */
    public function getOrCreateThread(Ride $ride, User $user): Conversation
    {
        // 1. Authorize the user (must be rider, driver, or admin)
        $this->authorizeAccess($ride, $user);

        // 2. Check if a conversation thread already exists
        $conversation = Conversation::where('ride_id', $ride->id)->first();

        if ($conversation) {
            return $conversation;
        }

        // 3. Ensure ride has driver assigned
        if (is_null($ride->driverProfile)) {
            throw ValidationException::withMessages([
                'ride_id' => ['Cannot start conversation because no driver is assigned to this ride yet.'],
            ]);
        }

        // 4. Create new thread
        return Conversation::create([
            'ride_id' => $ride->id,
            'driver_id' => $ride->driverProfile->user_id,
            'rider_id' => $ride->rider_id,
        ]);
    }

    /**
     * Load a conversation by thread ID with IDOR validation.
     */
    public function getThread(int $id, User $user): Conversation
    {
        $conversation = Conversation::with(['ride', 'driver', 'rider'])->findOrFail($id);

        $this->authorizeAccess($conversation->ride, $user);

        return $conversation;
    }

    /**
     * Helper to validate that the user is the rider, driver, or admin.
     */
    public function authorizeAccess(Ride $ride, User $user): void
    {
        if ($user->role->value === 'admin') {
            return;
        }

        $isRider = (int) $ride->rider_id === (int) $user->id;
        $isDriver = $ride->driverProfile && (int) $ride->driverProfile->user_id === (int) $user->id;

        if (! $isRider && ! $isDriver) {
            throw new AccessDeniedHttpException('You are not authorized to access this conversation.');
        }
    }
}
