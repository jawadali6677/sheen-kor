<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class AlertEngagementUpdated implements ShouldBroadcastNow, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public int $alertId,
        public int $likesCount,
        public int $commentsCount,
    ) {}

    /**
     * @return list<Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('alerts.'.$this->alertId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'AlertEngagementUpdated';
    }

    /**
     * @return array{alert_id: int, likes_count: int, comments_count: int}
     */
    public function broadcastWith(): array
    {
        return [
            'alert_id' => $this->alertId,
            'likes_count' => $this->likesCount,
            'comments_count' => $this->commentsCount,
        ];
    }
}
