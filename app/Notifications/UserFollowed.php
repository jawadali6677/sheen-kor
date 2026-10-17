<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class UserFollowed extends Notification
{
    public function __construct(public User $actor) {}

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
        return [
            'kind' => 'user_followed',
            'title' => $this->actor->name.' followed you',
            'body' => filled($this->actor->username) ? '@'.$this->actor->username : '',
            'url' => filled($this->actor->username)
                ? route('users.show', $this->actor)
                : url('/'),
            'actor_id' => $this->actor->id,
            'actor_name' => $this->actor->name,
            'actor_username' => $this->actor->username,
            'actor_avatar_url' => $this->actor->avatarUrl(),
            'post_id' => null,
            'alert_id' => null,
            'conversation_id' => null,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return (new BroadcastMessage($this->toArray($notifiable)))
            ->onConnection('sync');
    }
}
