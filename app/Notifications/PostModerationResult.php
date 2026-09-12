<?php

namespace App\Notifications;

use App\Models\Post;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class PostModerationResult extends Notification
{
    public const OutcomePublished = 'published';

    public const OutcomeRejected = 'rejected';

    public const RejectedBody = 'Your story was not published because it did not meet our community guidelines. Please review the guidelines and try again with content that is a good fit for this community.';

    public function __construct(public Post $post, public string $outcome) {}

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
        $published = $this->outcome === self::OutcomePublished;

        return [
            'kind' => $published ? 'post_published' : 'post_rejected',
            'title' => $published
                ? 'Your story was published'
                : 'Your story was not approved',
            'body' => $published
                ? 'A reviewer published '.$this->post->title.'. Thank you for sharing it with the community.'
                : self::RejectedBody,
            'url' => route('posts.show', $this->post),
            'actor_id' => null,
            'actor_name' => '',
            'actor_avatar_url' => null,
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
