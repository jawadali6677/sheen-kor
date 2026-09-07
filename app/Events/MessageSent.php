<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcastNow, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Message $message)
    {
        $this->message->loadMissing(['user', 'conversation.participants']);
    }

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('conversations.'.$this->message->conversation_id),
        ];

        foreach ($this->message->conversation->participants as $participant) {
            if ($participant->id === $this->message->user_id) {
                continue;
            }

            $channels[] = new PrivateChannel('users.'.$participant->id.'.conversations');
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'MessageSent';
    }

    /**
     * @return array{
     *     id: int,
     *     conversation_id: int,
     *     body: string,
     *     created_at: string|null,
     *     created_at_human: string|null,
     *     user: array{id: int|null, name: string, avatar_url: string|null}
     * }
     */
    public function broadcastWith(): array
    {
        return $this->message->toChatPayload();
    }
}
