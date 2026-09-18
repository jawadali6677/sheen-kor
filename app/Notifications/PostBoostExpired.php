<?php

namespace App\Notifications;

use App\Models\PostBoost;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class PostBoostExpired extends Notification
{
    public function __construct(public PostBoost $boost) {}

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
        $post = $this->boost->post;

        return [
            'kind' => 'post_boost_expired',
            'title' => 'Your post boost has ended',
            'body' => $post !== null
                ? 'The boost on “'.$post->title.'” has expired.'
                : 'Your post boost has expired.',
            'url' => $post !== null ? route('posts.show', $post) : url('/'),
            'actor_id' => null,
            'actor_name' => '',
            'actor_avatar_url' => null,
            'post_id' => $post?->id,
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
