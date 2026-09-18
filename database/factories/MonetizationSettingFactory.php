<?php

namespace Database\Factories;

use App\Models\MonetizationSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MonetizationSetting>
 */
class MonetizationSettingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => fake()->unique()->slug(2),
            'value' => fake()->word(),
        ];
    }
}
