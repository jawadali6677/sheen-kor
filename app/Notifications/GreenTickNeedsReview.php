<?php

namespace App\Notifications;

use App\Models\UserVerification;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class GreenTickNeedsReview extends Notification
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
        $applicant = $this->verification->user;

        return [
            'kind' => 'green_tick_needs_review',
            'title' => 'Green Tick needs review',
            'body' => ($applicant?->name ?? 'Unknown').' requested '.$this->verification->package_name.'.',
            'url' => route('admin.monetization.green-ticks.show', $this->verification),
            'actor_id' => $applicant?->id,
            'actor_name' => $applicant?->name ?? '',
            'actor_avatar_url' => $applicant?->avatarUrl(),
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
