<?php

namespace App\Notifications;

use App\Models\UserVerification;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class GreenTickExpired extends Notification
{
    public function __construct(public UserVerification $verification) {}

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
            'kind' => 'green_tick_expired',
            'title' => 'Your Green Tick has expired',
            'body' => 'Your Green Tick is no longer active. You can request a new one from your profile.',
            'url' => route('profile.edit'),
            'actor_id' => null,
            'actor_name' => '',
            'actor_avatar_url' => null,
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
