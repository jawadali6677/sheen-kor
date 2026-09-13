<?php

namespace Database\Factories;

use App\Models\MarketListing;
use Illuminate\Database\Eloquent\Factories\Factory;

class MarketListingImageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'market_listing_id' => MarketListing::factory(),
            'image' => 'market/images/placeholder.jpg',
            'caption' => fake()->sentence(),
            'sort_order' => fake()->numberBetween(0, 5),
            'media_type' => 'image',
        ];
    }
}
