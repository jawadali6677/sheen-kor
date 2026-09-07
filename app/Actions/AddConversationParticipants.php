<?php

namespace App\Actions;

use App\Enums\ConversationParticipantRole;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AddConversationParticipants
{
    /**
     * @param  list<int>  $participantIds
     */
    public function handle(User $actor, Conversation $conversation, array $participantIds): void
    {
        $ids = collect($participantIds)
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            throw ValidationException::withMessages([
                'user_ids' => 'Select at least one person to add.',
            ]);
        }

        $existingIds = $conversation->participants()->pluck('users.id');
        $newIds = $ids->diff($existingIds)->values();

        if ($newIds->isEmpty()) {
            throw ValidationException::withMessages([
                'user_ids' => 'Those people are already in this conversation.',
            ]);
        }

        $users = User::query()
            ->whereIn('id', $newIds)
            ->where('status', true)
            ->get();

        if ($users->count() !== $newIds->count()) {
            throw ValidationException::withMessages([
                'user_ids' => 'Every added member must be an active user.',
            ]);
        }

        $followedCount = $actor->followings()
            ->whereIn('users.id', $newIds)
            ->count();

        if ($followedCount !== $newIds->count()) {
            throw ValidationException::withMessages([
                'user_ids' => 'You can only add people you follow.',
            ]);
        }

        DB::transaction(function () use ($conversation, $users): void {
            $attachments = [];

            foreach ($users as $user) {
                $attachments[$user->id] = ['role' => ConversationParticipantRole::Member->value];
            }

            $conversation->participants()->attach($attachments);
        });
    }
}
