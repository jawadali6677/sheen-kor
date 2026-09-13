<?php

namespace App\Notifications;

use App\Models\MarketListing;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class MarketListingNeedsReview extends Notification
{
    public function __construct(public MarketListing $listing) {}

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
        $owner = $this->listing->user;

        return [
            'kind' => 'market_listing_needs_review',
            'title' => 'Listing needs review',
            'body' => ($owner?->name ?? 'Unknown').' · '.$this->listing->title,
            'url' => route('admin.market.show', $this->listing),
            'actor_id' => $owner?->id,
            'actor_name' => $owner?->name ?? '',
            'actor_avatar_url' => $owner?->avatarUrl(),
            'post_id' => null,
            'alert_id' => null,
            'conversation_id' => null,
            'listing_id' => $this->listing->id,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return (new BroadcastMessage($this->toArray($notifiable)))
            ->onConnection('sync');
    }
}
