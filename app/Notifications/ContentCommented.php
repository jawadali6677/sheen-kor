<?php

namespace App\Notifications;

use App\Models\Alert;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class ContentCommented extends Notification
{
    public function __construct(
        public User $actor,
        public Model $commentable,
        public Comment $comment,
    ) {}

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
        $isAlert = $this->commentable instanceof Alert;
        $noun = $isAlert ? 'alert' : 'post';

        return [
            'kind' => $isAlert ? 'alert_commented' : 'post_commented',
            'title' => $this->actor->name.' commented on your '.$noun,
            'body' => Str::limit($this->comment->content, 80),
            'url' => $isAlert
                ? route('alerts.show', $this->commentable)
                : route('posts.show', $this->commentable),
            'actor_id' => $this->actor->id,
            'actor_name' => $this->actor->name,
            'actor_avatar_url' => $this->actor->avatarUrl(),
            'post_id' => $this->commentable instanceof Post ? $this->commentable->id : null,
            'alert_id' => $isAlert ? $this->commentable->id : null,
            'comment_id' => $this->comment->id,
            'conversation_id' => null,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return (new BroadcastMessage($this->toArray($notifiable)))
            ->onConnection('sync');
    }
}
