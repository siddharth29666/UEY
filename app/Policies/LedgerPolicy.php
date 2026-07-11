<?php

namespace App\Policies;

use App\Models\Ledger;
use App\Models\User;

class LedgerPolicy
{
    /**
     * Only admins may list/view all ledger entries.
     */
    public function viewAny(User $user): bool
    {
        return $user->role->value === 'admin';
    }

    /**
     * Admins can view any individual ledger entry.
     */
    public function view(User $user, Ledger $ledger): bool
    {
        return $user->role->value === 'admin';
    }
}
