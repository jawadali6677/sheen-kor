<?php

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('conversations.{conversation}', function (User $user, Conversation $conversation): bool {
    return $conversation->hasParticipant($user);
});

Broadcast::channel('users.{id}.conversations', function (User $user, int $id): bool {
    return $user->id === $id;
});

Broadcast::channel('App.Models.User.{id}', function (User $user, int $id): bool {
    return $user->id === $id;
});
