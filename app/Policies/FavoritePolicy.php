<?php

namespace App\Policies;

use App\Models\Favorite;
use App\Models\User;

/**
 * Second line of defense behind the already user_id-scoped lookup that
 * finds the favorite to delete in the first place.
 */
class FavoritePolicy
{
    public function delete(User $authUser, Favorite $favorite): bool
    {
        return $authUser->id === $favorite->user_id;
    }
}
