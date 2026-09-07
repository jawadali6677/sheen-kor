<?php

namespace App\Actions;

use App\Enums\ConversationParticipantRole;
use App\Enums\ConversationType;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateGroupConversation
{
    /**
     * @param  list<int>  $participantIds
     */
    public function handle(User $actor, string $title, array $participantIds): Conversation
    {
        $otherIds = collect($participantIds)
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->reject(fn (int $id): bool => $id === $actor->id)
            ->values();

        if ($otherIds->count() < 2) {
            throw ValidationException::withMessages([
                'user_ids' => 'A group needs at least two other people.',
            ]);
        }

        $others = User::query()
            ->whereIn('id', $otherIds)
            ->where('status', true)
            ->get();

        if ($others->count() !== $otherIds->count()) {
            throw ValidationException::withMessages([
                'user_ids' => 'Every group member must be an active user.',
            ]);
        }

        $followedCount = $actor->followings()
            ->whereIn('users.id', $otherIds)
            ->count();

        if ($followedCount !== $otherIds->count()) {
            throw ValidationException::withMessages([
                'user_ids' => 'You can only add people you follow.',
            ]);
        }

        return DB::transaction(function () use ($actor, $title, $others): Conversation {
            $conversation = Conversation::query()->create([
                'type' => ConversationType::Group,
                'title' => $title,
                'created_by' => $actor->id,
            ]);

            $attachments = [
                $actor->id => ['role' => ConversationParticipantRole::Admin->value],
            ];

            foreach ($others as $other) {
                $attachments[$other->id] = ['role' => ConversationParticipantRole::Member->value];
            }

            $conversation->participants()->attach($attachments);

            return $conversation->load('participants');
        });
    }
}
