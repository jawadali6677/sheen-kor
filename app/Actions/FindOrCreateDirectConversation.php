<?php

namespace App\Actions;

use App\Enums\ConversationParticipantRole;
use App\Enums\ConversationType;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FindOrCreateDirectConversation
{
    public function handle(User $actor, User $other): Conversation
    {
        if ($actor->is($other)) {
            throw ValidationException::withMessages([
                'user_id' => 'You cannot start a conversation with yourself.',
            ]);
        }

        if (! $other->status) {
            throw ValidationException::withMessages([
                'user_id' => 'You cannot message an inactive user.',
            ]);
        }

        $pairKey = Conversation::pairKey($actor->id, $other->id);

        return DB::transaction(function () use ($actor, $other, $pairKey): Conversation {
            $conversation = Conversation::query()
                ->where('pair_key', $pairKey)
                ->lockForUpdate()
                ->first();

            if ($conversation !== null) {
                return $conversation;
            }

            $conversation = Conversation::query()->create([
                'type' => ConversationType::Direct,
                'title' => null,
                'pair_key' => $pairKey,
                'created_by' => $actor->id,
            ]);

            $conversation->participants()->attach([
                $actor->id => ['role' => ConversationParticipantRole::Member->value],
                $other->id => ['role' => ConversationParticipantRole::Member->value],
            ]);

            return $conversation;
        });
    }
}
