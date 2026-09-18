<?php

namespace Database\Factories;

use App\Enums\ListingPromotionPlacement;
use App\Enums\ListingPromotionSource;
use App\Enums\ListingPromotionStatus;
use App\Enums\MonetizationPackageType;
use App\Models\ListingPromotion;
use App\Models\MarketListing;
use App\Models\MonetizationPackage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ListingPromotion>
 */
class ListingPromotionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $package = MonetizationPackage::query()
            ->where('type', MonetizationPackageType::ListingPromotion)
            ->where('slug', 'listing_featured_7d')
            ->first();

        return [
            'user_id' => User::factory(),
            'market_listing_id' => MarketListing::factory(),
            'package_id' => $package?->id,
            'order_id' => null,
            'status' => ListingPromotionStatus::Pending,
            'source' => ListingPromotionSource::Request,
            'placement' => $package?->placement ?? ListingPromotionPlacement::FeaturedHome->value,
            'package_type' => MonetizationPackageType::ListingPromotion->value,
            'package_name' => $package?->name ?? 'Featured Listing - 7 Days',
            'package_slug' => $package?->slug ?? 'listing_featured_7d',
            'duration_days' => $package?->duration_days ?? 7,
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
            'status' => ListingPromotionStatus::Active,
            'starts_at' => now(),
            'ends_at' => now()->addDays((int) ($attributes['duration_days'] ?? 7)),
            'activated_at' => now(),
        ]);
    }
}
