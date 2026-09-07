<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Validation\ValidationException;

class FollowUser
{
    public function handle(User $actor, User $user): void
    {
        if ($actor->is($user)) {
            throw ValidationException::withMessages([
                'user' => 'You cannot follow yourself.',
            ]);
        }

        if (! $user->status) {
            throw ValidationException::withMessages([
                'user' => 'You cannot follow an inactive user.',
            ]);
        }

        $actor->followings()->syncWithoutDetaching([$user->id]);
    }
}
