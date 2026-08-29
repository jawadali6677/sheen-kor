<?php

namespace Database\Factories;

use App\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;

class PostImageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'post_id' => Post::factory(),

            'image' => 'posts/placeholder.jpg',

            'caption' => fake()->sentence(),

            'sort_order' => fake()->numberBetween(0, 5),
        ];
    }
}