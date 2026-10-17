<?php

namespace App\Actions;

use App\Models\User;
use App\Notifications\UserFollowed;
use Illuminate\Validation\ValidationException;
use Throwable;

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

        $changes = $actor->followings()->syncWithoutDetaching([$user->id]);

        if ($changes['attached'] === []) {
            return;
        }

        $this->notifyFollowed($actor, $user);
    }

    private function notifyFollowed(User $actor, User $user): void
    {
        $alreadyNotified = $user->unreadNotifications()
            ->where('type', UserFollowed::class)
            ->where('data->actor_id', $actor->id)
            ->exists();

        if ($alreadyNotified) {
            return;
        }

        try {
            $user->notifyInbox(new UserFollowed($actor));
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
