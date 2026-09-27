<?php

namespace Database\Factories;

use App\Models\Post;
use App\Models\PostView;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PostView>
 */
class PostViewFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'post_id' => Post::factory(),
            'user_id' => function (array $attributes): int {
                return (int) Post::query()->whereKey($attributes['post_id'])->value('user_id');
            },
            'viewer_key' => 'user:'.fake()->unique()->numberBetween(1_000_000, 9_000_000),
            'viewed_on' => now()->toDateString(),
        ];
    }
}
