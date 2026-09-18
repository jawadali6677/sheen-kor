<?php

namespace App\Notifications;

use App\Enums\UserVerificationStatus;
use App\Models\UserVerification;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class GreenTickReviewResult extends Notification
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
        $approved = $this->verification->status === UserVerificationStatus::Active;

        return [
            'kind' => $approved ? 'green_tick_approved' : 'green_tick_rejected',
            'title' => $approved ? 'Your Green Tick was approved' : 'Your Green Tick request was not approved',
            'body' => $approved
                ? 'Your Green Tick is active until '.$this->verification->ends_at?->toFormattedDateString().'.'
                : 'A reviewer did not approve your Green Tick request.',
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
