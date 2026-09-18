<?php

namespace App\Enums;

enum PaymentProvider: string
{
    case Manual = 'manual';
    case Stripe = 'stripe';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manual',
            self::Stripe => 'Stripe',
        };
    }
}
