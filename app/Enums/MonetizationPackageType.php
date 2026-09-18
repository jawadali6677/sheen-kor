<?php

namespace App\Enums;

enum MonetizationPackageType: string
{
    case GreenTick = 'green_tick';
    case PostBoost = 'post_boost';
    case ListingPromotion = 'listing_promotion';
    case RewardedBoost = 'rewarded_boost';

    public function label(): string
    {
        return match ($this) {
            self::GreenTick => 'Green Tick',
            self::PostBoost => 'Post Boost',
            self::ListingPromotion => 'Listing Promotion',
            self::RewardedBoost => 'Rewarded Boost',
        };
    }
}
