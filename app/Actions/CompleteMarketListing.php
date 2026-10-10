<?php

namespace App\Actions;

use App\Enums\ListingPromotionStatus;
use App\Enums\MarketListingStatus;
use App\Models\MarketListing;

class CompleteMarketListing
{
    public function handle(MarketListing $listing, MarketListingStatus $status): void
    {
        $listing->update([
            'status' => $status,
            'closed_at' => now(),
        ]);

        $openPromotions = $listing->promotions()
            ->whereIn('status', [
                ListingPromotionStatus::Pending->value,
                ListingPromotionStatus::Active->value,
            ])
            ->with('order')
            ->get();

        foreach ($openPromotions as $promotion) {
            $promotion->order?->cancelIfPending();
        }

        $listing->promotions()
            ->whereIn('status', [
                ListingPromotionStatus::Pending->value,
                ListingPromotionStatus::Active->value,
            ])
            ->update([
                'status' => ListingPromotionStatus::Cancelled->value,
            ]);
    }
}
