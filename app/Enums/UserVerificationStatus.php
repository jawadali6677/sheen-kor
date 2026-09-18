<?php

namespace App\Enums;

enum UserVerificationStatus: string
{
    case PendingPayment = 'pending_payment';
    case PendingReview = 'pending_review';
    case Active = 'active';
    case Rejected = 'rejected';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PendingPayment => 'Pending payment',
            self::PendingReview => 'Pending review',
            self::Active => 'Active',
            self::Rejected => 'Rejected',
            self::Expired => 'Expired',
            self::Cancelled => 'Cancelled',
        };
    }
}
