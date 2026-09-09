<?php

namespace App\Notifications;

use App\Models\Message;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class ConversationMessageReceived extends Notification
{
    public function __construct(public Message $message)
    {
        $this->message->loadMissing('user');
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $actor = $this->message->user;

        return [
            'kind' => 'conversation_message',
            'title' => ($actor?->name ?? 'Someone').' sent you a message',
            'body' => Str::limit($this->message->body, 80),
            'url' => route('messages.show', $this->message->conversation_id),
            'actor_id' => $actor?->id,
            'actor_name' => $actor?->name ?? 'Unknown User',
            'actor_avatar_url' => $actor?->avatarUrl(),
            'conversation_id' => $this->message->conversation_id,
            'message_id' => $this->message->id,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return (new BroadcastMessage($this->toArray($notifiable)))
            ->onConnection('sync');
    }
}
