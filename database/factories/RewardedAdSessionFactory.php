<?php

namespace Database\Factories;

use App\Enums\RewardedAdStatus;
use App\Enums\RewardType;
use App\Models\Advertisement;
use App\Models\RewardedAdSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RewardedAdSession>
 */
class RewardedAdSessionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'advertisement_id' => Advertisement::factory()->state([
                'is_feed' => false,
                'is_sidebar' => false,
                'is_rewarded' => true,
            ]),
            'status' => RewardedAdStatus::Started,
            'reward_type' => RewardType::ProfileVisibilityCredit,
            'reward_value' => 1,
            'completion_token' => (string) Str::uuid(),
            'provider_reference' => null,
            'started_at' => now(),
            'completed_at' => null,
            'failed_at' => null,
            'notes' => null,
        ];
    }
}
