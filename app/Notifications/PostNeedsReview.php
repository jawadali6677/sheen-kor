<?php

namespace App\Notifications;

use App\Models\Post;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class PostNeedsReview extends Notification
{
    public function __construct(public Post $post) {}

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
        $author = $this->post->user;

        return [
            'kind' => 'post_needs_review',
            'title' => 'Story needs review',
            'body' => ($author?->name ?? 'Unknown').' · '.$this->post->title,
            'url' => route('admin.posts.show', $this->post),
            'actor_id' => $author?->id,
            'actor_name' => $author?->name ?? '',
            'actor_avatar_url' => $author?->avatarUrl(),
            'post_id' => $this->post->id,
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
