<?php

namespace App\Policies;

use App\Models\User;

/**
 * Second line of defense behind the fact that both profile forms only ever
 * operate on Auth::user() and never read an identifier from the request.
 */
class UpdateProfilePolicy
{
    public function update(User $authUser, User $target): bool
    {
        return $authUser->is($target);
    }
}
