<?php

namespace Database\Factories;

use App\Enums\MonetizationPackageType;
use App\Enums\PostBoostSource;
use App\Enums\PostBoostStatus;
use App\Models\MonetizationPackage;
use App\Models\Post;
use App\Models\PostBoost;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PostBoost>
 */
class PostBoostFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $package = MonetizationPackage::query()
            ->where('type', MonetizationPackageType::PostBoost)
            ->where('slug', 'post_boost_1d')
            ->first();

        $post = Post::factory();

        return [
            'user_id' => User::factory(),
            'post_id' => $post,
            'package_id' => $package?->id,
            'order_id' => null,
            'status' => PostBoostStatus::Pending,
            'source' => PostBoostSource::Request,
            'package_type' => MonetizationPackageType::PostBoost->value,
            'package_name' => $package?->name ?? 'Post Boost - 1 Day',
            'package_slug' => $package?->slug ?? 'post_boost_1d',
            'duration_days' => $package?->duration_days ?? 1,
            'price' => $package?->price ?? '0.00',
            'currency' => $package?->currency ?? 'USD',
            'starts_at' => null,
            'ends_at' => null,
            'activated_by' => null,
            'activated_at' => null,
            'notes' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PostBoostStatus::Active,
            'starts_at' => now(),
            'ends_at' => now()->addDays((int) ($attributes['duration_days'] ?? 1)),
            'activated_at' => now(),
        ]);
    }
}
