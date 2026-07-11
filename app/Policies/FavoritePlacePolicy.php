<?php

namespace App\Policies;

use App\Models\FavoritePlace;
use App\Models\User;

class FavoritePlacePolicy
{
    /**
     * Determine whether the user can view the favorite place.
     */
    public function view(User $user, FavoritePlace $favoritePlace): bool
    {
        return $user->id === $favoritePlace->user_id;
    }

    /**
     * Determine whether the user can update the favorite place.
     */
    public function update(User $user, FavoritePlace $favoritePlace): bool
    {
        return $user->id === $favoritePlace->user_id;
    }

    /**
     * Determine whether the user can delete the favorite place.
     */
    public function delete(User $user, FavoritePlace $favoritePlace): bool
    {
        return $user->id === $favoritePlace->user_id;
    }
}
