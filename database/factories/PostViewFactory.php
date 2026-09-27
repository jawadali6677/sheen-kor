<?php

namespace Database\Factories;

use App\Models\Post;
use App\Models\PostView;
use App\Models\User;
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
            'viewer_user_id' => User::factory(),
            'viewer_key' => function (array $attributes): string {
                $viewerId = $attributes['viewer_user_id'];

                return 'user:'.(is_object($viewerId) ? $viewerId->getKey() : $viewerId);
            },
            'viewed_on' => now()->toDateString(),
        ];
    }
}
