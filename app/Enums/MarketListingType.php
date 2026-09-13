<?php

namespace App\Enums;

enum MarketListingType: string
{
    case Sell = 'sell';
    case GiveAway = 'give_away';
    case Exchange = 'exchange';
    case Donate = 'donate';

    public function label(): string
    {
        return match ($this) {
            self::Sell => 'Sell',
            self::GiveAway => 'Give Away',
            self::Exchange => 'Exchange',
            self::Donate => 'Donate',
        };
    }

    public function priceIsRequired(): bool
    {
        return $this === self::Sell;
    }

    public function exchangeDetailsAreRequired(): bool
    {
        return $this === self::Exchange;
    }

    public function catalogOfferLabel(?string $price): string
    {
        return match ($this) {
            self::Sell => filled($price) ? 'Price '.$price : 'Price',
            self::Exchange => 'Exchange',
            self::GiveAway, self::Donate => 'Free',
        };
    }

    public function canBeMarkedSold(): bool
    {
        return $this === self::Sell;
    }

    public function canBeMarkedExchanged(): bool
    {
        return $this === self::Exchange;
    }

    public function canBeMarkedDonated(): bool
    {
        return $this === self::GiveAway || $this === self::Donate;
    }
}
