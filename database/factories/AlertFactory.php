<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class AlertFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->sentence(rand(4, 8));

        return [
            'user_id' => User::factory(),
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(1000, 9999),
            'description' => fake()->paragraphs(3, true),
            'location_name' => fake()->city().', '.fake()->country(),
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
            'featured_image' => 'alerts/featured/placeholder.jpg',
            'severity' => fake()->randomElement(['low', 'medium', 'high']),
            'status' => 'open',
            'views' => fake()->numberBetween(0, 500),
        ];
    }
}
