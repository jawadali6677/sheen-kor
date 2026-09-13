<?php

namespace App\Notifications;

use App\Models\MarketListing;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class MarketListingModerationResult extends Notification
{
    public const OutcomePublished = 'published';

    public const OutcomeRejected = 'rejected';

    public const RejectedBody = 'Your listing was not published because it did not meet our community guidelines. Please review the guidelines and try again with content that is a good fit for this community.';

    public function __construct(public MarketListing $listing, public string $outcome) {}

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
            'kind' => $published ? 'market_listing_published' : 'market_listing_rejected',
            'title' => $published
                ? 'Your listing was published'
                : 'Your listing was not approved',
            'body' => $published
                ? 'A reviewer published '.$this->listing->title.'. Thank you for sharing it with the community.'
                : self::RejectedBody,
            'url' => route('market.show', $this->listing),
            'actor_id' => null,
            'actor_name' => '',
            'actor_avatar_url' => null,
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
