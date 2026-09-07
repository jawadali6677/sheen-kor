<?php

namespace Database\Factories;

use App\Enums\ConversationType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ConversationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'type' => ConversationType::Group,
            'title' => fake()->words(3, true),
            'pair_key' => null,
            'created_by' => User::factory(),
            'last_message_at' => null,
        ];
    }

    public function direct(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => ConversationType::Direct,
            'title' => null,
        ]);
    }
}
