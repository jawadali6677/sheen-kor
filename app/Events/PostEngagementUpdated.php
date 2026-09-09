<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class PostEngagementUpdated implements ShouldBroadcastNow, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public int $postId,
        public int $likesCount,
        public int $commentsCount,
    ) {}

    /**
     * @return list<Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('posts.'.$this->postId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'PostEngagementUpdated';
    }

    /**
     * @return array{post_id: int, likes_count: int, comments_count: int}
     */
    public function broadcastWith(): array
    {
        return [
            'post_id' => $this->postId,
            'likes_count' => $this->likesCount,
            'comments_count' => $this->commentsCount,
        ];
    }
}
