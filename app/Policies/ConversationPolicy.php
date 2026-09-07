<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;

class ConversationPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Conversation $conversation): bool
    {
        if ($conversation->hasParticipant($user)) {
            return true;
        }

        return $user->isAdmin() && $conversation->isGroup();
    }

    public function send(User $user, Conversation $conversation): bool
    {
        return $conversation->hasParticipant($user);
    }

    public function addParticipants(User $user, Conversation $conversation): bool
    {
        return $this->manageGroup($user, $conversation);
    }

    public function removeParticipant(User $user, Conversation $conversation): bool
    {
        return $this->manageGroup($user, $conversation);
    }

    public function delete(User $user, Conversation $conversation): bool
    {
        return $this->manageGroup($user, $conversation);
    }

    public function leave(User $user, Conversation $conversation): bool
    {
        return $conversation->hasParticipant($user);
    }

    private function manageGroup(User $user, Conversation $conversation): bool
    {
        if (! $conversation->isGroup()) {
            return false;
        }

        return $user->isAdmin() || $conversation->isAdmin($user);
    }
}
