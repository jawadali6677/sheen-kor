<?php

namespace Database\Factories;

use App\Enums\ScoreReason;
use App\Models\ScoreEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScoreEvent>
 */
class ScoreEventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'reason' => ScoreReason::PostCreated,
            'points' => 10,
            'source_type' => 'post',
            'source_id' => fake()->unique()->numberBetween(1, 999999),
        ];
    }
}
