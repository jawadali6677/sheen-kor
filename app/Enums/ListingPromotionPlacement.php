<?php

namespace App\Enums;

enum ListingPromotionPlacement: string
{
    case FeaturedHome = 'featured_home';
    case BoostRank = 'boost_rank';
    case TopOfCategory = 'top_of_category';

    public function label(): string
    {
        return match ($this) {
            self::FeaturedHome => 'Featured Listing',
            self::BoostRank => 'Promoted',
            self::TopOfCategory => 'Top of Category',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::FeaturedHome => 'Featured',
            self::BoostRank => 'Promoted',
            self::TopOfCategory => 'Top of category',
        };
    }
}
