<?php

namespace Database\Factories;

use App\Enums\MarketListingCondition;
use App\Enums\MarketListingStatus;
use App\Enums\MarketListingType;
use App\Models\MarketCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class MarketListingFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->sentence(rand(4, 8));

        return [
            'user_id' => User::factory(),
            'market_category_id' => MarketCategory::factory(),
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(1000, 9999),
            'description' => fake()->paragraphs(2, true),
            'listing_type' => MarketListingType::Sell,
            'condition' => MarketListingCondition::Good,
            'price' => fake()->randomFloat(2, 1, 500),
            'exchange_details' => null,
            'location_name' => fake()->city().', '.fake()->country(),
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
            'featured_image' => 'market/featured/placeholder.jpg',
            'status' => MarketListingStatus::Published,
            'published_at' => now()->subDays(rand(0, 30)),
            'closed_at' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MarketListingStatus::Pending,
            'published_at' => null,
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MarketListingStatus::Rejected,
            'published_at' => null,
        ]);
    }

    public function sold(): static
    {
        return $this->state(fn (array $attributes) => [
            'listing_type' => MarketListingType::Sell,
            'status' => MarketListingStatus::Sold,
            'closed_at' => now(),
        ]);
    }

    public function giveAway(): static
    {
        return $this->state(fn (array $attributes) => [
            'listing_type' => MarketListingType::GiveAway,
            'price' => null,
            'exchange_details' => null,
        ]);
    }

    public function exchange(): static
    {
        return $this->state(fn (array $attributes) => [
            'listing_type' => MarketListingType::Exchange,
            'price' => null,
            'exchange_details' => fake()->sentence(),
        ]);
    }

    public function donate(): static
    {
        return $this->state(fn (array $attributes) => [
            'listing_type' => MarketListingType::Donate,
            'price' => null,
            'exchange_details' => null,
        ]);
    }
}
