<?php

namespace App\Notifications;

use App\Models\ListingPromotion;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class ListingPromotionExpired extends Notification
{
    public function __construct(public ListingPromotion $promotion) {}

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
        $listing = $this->promotion->listing;

        return [
            'kind' => 'listing_promotion_expired',
            'title' => 'Your listing promotion has ended',
            'body' => $listing !== null
                ? 'The promotion on “'.$listing->title.'” has expired.'
                : 'Your listing promotion has expired.',
            'url' => $listing !== null ? route('market.show', $listing) : route('market.index'),
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
