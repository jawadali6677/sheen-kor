<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PostFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->sentence(rand(5, 10));

        return [
            'user_id' => User::factory(),

            'category_id' => Category::inRandomOrder()->first()?->id,

            'title' => $title,

            'slug' => Str::slug($title) . '-' . fake()->unique()->numberBetween(1000, 9999),

            'excerpt' => fake()->paragraph(2),

            'content' => fake()->paragraphs(5, true),

            'featured_image' => null,

            'status' => 'published',

            'published_at' => now()->subDays(rand(0, 30)),

            'views' => fake()->numberBetween(0, 5000),
        ];
    }
}