<?php

namespace App\Actions;

use App\Models\User;

class UnfollowUser
{
    public function handle(User $actor, User $user): void
    {
        $actor->followings()->detach($user->id);
    }
}
