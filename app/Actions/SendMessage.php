<?php

namespace App\Actions;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SendMessage
{
    public function handle(User $sender, Conversation $conversation, string $body): Message
    {
        return DB::transaction(function () use ($sender, $conversation, $body): Message {
            $message = $conversation->messages()->create([
                'user_id' => $sender->id,
                'body' => $body,
            ]);

            $conversation->update([
                'last_message_at' => $message->created_at,
            ]);

            $conversation->participants()->updateExistingPivot($sender->id, [
                'last_read_at' => $message->created_at,
            ]);

            return $message->load('user');
        });
    }
}
