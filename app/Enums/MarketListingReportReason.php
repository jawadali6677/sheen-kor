<?php

namespace App\Enums;

enum MarketListingReportReason: string
{
    case Spam = 'spam';
    case Scam = 'scam';
    case Inappropriate = 'inappropriate';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Spam => 'Spam',
            self::Scam => 'Scam',
            self::Inappropriate => 'Inappropriate',
            self::Other => 'Other',
        };
    }
}
