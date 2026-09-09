<?php

namespace App\Notifications;

use App\Models\Alert;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class ContentLiked extends Notification
{
    public function __construct(public User $actor, public Model $likeable) {}

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
        $isAlert = $this->likeable instanceof Alert;
        $noun = $isAlert ? 'alert' : 'post';

        return [
            'kind' => $isAlert ? 'alert_liked' : 'post_liked',
            'title' => $this->actor->name.' liked your '.$noun,
            'body' => (string) $this->likeable->title,
            'url' => $isAlert
                ? route('alerts.show', $this->likeable)
                : route('posts.show', $this->likeable),
            'actor_id' => $this->actor->id,
            'actor_name' => $this->actor->name,
            'actor_avatar_url' => $this->actor->avatarUrl(),
            'post_id' => $this->likeable instanceof Post ? $this->likeable->id : null,
            'alert_id' => $isAlert ? $this->likeable->id : null,
            'conversation_id' => null,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return (new BroadcastMessage($this->toArray($notifiable)))
            ->onConnection('sync');
    }
}
