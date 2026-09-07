<?php

namespace Database\Factories;

use App\Models\Alert;
use Illuminate\Database\Eloquent\Factories\Factory;

class AlertImageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'alert_id' => Alert::factory(),
            'image' => 'alerts/images/placeholder.jpg',
            'caption' => fake()->sentence(),
            'sort_order' => fake()->numberBetween(0, 5),
            'kind' => 'report',
            'media_type' => 'image',
        ];
    }
}
