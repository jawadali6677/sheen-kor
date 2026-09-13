<?php

namespace Database\Factories;

use App\Enums\MarketListingReportReason;
use App\Models\MarketListing;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MarketListingReportFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'market_listing_id' => MarketListing::factory(),
            'reason' => MarketListingReportReason::Spam,
            'details' => fake()->optional()->sentence(),
            'status' => 'pending',
        ];
    }
}
