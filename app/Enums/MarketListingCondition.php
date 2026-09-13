<?php

namespace App\Enums;

enum MarketListingCondition: string
{
    case New = 'new';
    case LikeNew = 'like_new';
    case Good = 'good';
    case Fair = 'fair';
    case NeedsRepair = 'needs_repair';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::LikeNew => 'Like New',
            self::Good => 'Good',
            self::Fair => 'Fair',
            self::NeedsRepair => 'Needs Repair',
        };
    }
}
