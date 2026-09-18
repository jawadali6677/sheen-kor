<?php

namespace Database\Factories;

use App\Enums\MonetizationPackageType;
use App\Models\MonetizationPackage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MonetizationPackage>
 */
class MonetizationPackageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'type' => MonetizationPackageType::PostBoost,
            'name' => Str::title($name),
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1000, 9999),
            'duration_days' => 1,
            'placement' => null,
            'price' => '0.00',
            'currency' => 'USD',
            'is_enabled' => false,
            'sort_order' => 0,
        ];
    }
}
