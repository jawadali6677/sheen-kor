<?php

namespace Database\Factories;

use App\Enums\MonetizationPackageType;
use App\Enums\OrderStatus;
use App\Models\MonetizationPackage;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $package = MonetizationPackage::query()
            ->where('type', MonetizationPackageType::GreenTick)
            ->where('slug', 'green_tick_monthly')
            ->first();

        return [
            'user_id' => User::factory(),
            'package_id' => $package?->id,
            'post_id' => null,
            'market_listing_id' => null,
            'status' => OrderStatus::Pending,
            'amount' => $package?->price ?? '0.00',
            'currency' => $package?->currency ?? 'USD',
            'snapshot' => [
                'name' => $package?->name ?? 'Green Tick Monthly',
                'slug' => $package?->slug ?? 'green_tick_monthly',
                'type' => MonetizationPackageType::GreenTick->value,
                'duration_days' => $package?->duration_days ?? 30,
                'placement' => $package?->placement,
                'price' => $package?->price ?? '0.00',
            ],
        ];
    }
}
