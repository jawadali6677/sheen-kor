<?php

namespace App\Enums;

enum MarketListingStatus: string
{
    case Pending = 'pending';
    case Published = 'published';
    case Rejected = 'rejected';
    case Sold = 'sold';
    case Exchanged = 'exchanged';
    case Donated = 'donated';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Published => 'Published',
            self::Rejected => 'Rejected',
            self::Sold => 'Sold',
            self::Exchanged => 'Exchanged',
            self::Donated => 'Donated',
            self::Closed => 'Closed',
        };
    }

    public function isCompleted(): bool
    {
        return in_array($this, [
            self::Sold,
            self::Exchanged,
            self::Donated,
            self::Closed,
        ], true);
    }

    public function isEditable(): bool
    {
        return in_array($this, [
            self::Pending,
            self::Published,
            self::Rejected,
        ], true);
    }

    public function isPubliclyVisible(): bool
    {
        return $this === self::Published || $this->isCompleted();
    }
}
