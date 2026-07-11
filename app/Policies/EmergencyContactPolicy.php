<?php

namespace App\Policies;

use App\Models\EmergencyContact;
use App\Models\User;

class EmergencyContactPolicy
{
    /**
     * Determine whether the user can view the contact.
     */
    public function view(User $user, EmergencyContact $contact): bool
    {
        return $user->id === $contact->user_id;
    }

    /**
     * Determine whether the user can update the contact.
     */
    public function update(User $user, EmergencyContact $contact): bool
    {
        return $user->id === $contact->user_id;
    }

    /**
     * Determine whether the user can delete the contact.
     */
    public function delete(User $user, EmergencyContact $contact): bool
    {
        return $user->id === $contact->user_id;
    }
}
