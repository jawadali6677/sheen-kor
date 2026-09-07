<?php

namespace App\Actions;

use App\Enums\ConversationParticipantRole;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class LeaveConversation
{
    public function handle(User $user, Conversation $conversation): void
    {
        DB::transaction(function () use ($user, $conversation): void {
            $conversation->participants()->detach($user->id);

            $remaining = $conversation->participants()
                ->orderBy('conversation_user.created_at')
                ->orderBy('users.id')
                ->get();

            if ($remaining->isEmpty()) {
                $conversation->delete();

                return;
            }

            $hasAdmin = $remaining->contains(
                fn (User $participant): bool => $participant->pivot->role === ConversationParticipantRole::Admin->value,
            );

            if (! $hasAdmin) {
                $conversation->participants()->updateExistingPivot($remaining->first()->id, [
                    'role' => ConversationParticipantRole::Admin->value,
                ]);
            }
        });
    }
}
