<?php

namespace Database\Factories;

use App\Models\Advertisement;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Advertisement>
 */
class AdvertisementFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(4);

        return [
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(1000, 9999),
            'advertiser' => fake()->company(),
            'title' => $title,
            'description' => fake()->sentence(12),
            'cta' => 'Learn more',
            'image_url' => null,
            'destination_url' => 'https://example.com/ad',
            'is_feed' => true,
            'is_sidebar' => true,
            'is_video' => false,
            'is_rewarded' => false,
            'is_enabled' => true,
            'sort_order' => 0,
        ];
    }
}
