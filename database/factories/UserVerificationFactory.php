<?php

namespace Database\Factories;

use App\Enums\MonetizationPackageType;
use App\Enums\UserVerificationSource;
use App\Enums\UserVerificationStatus;
use App\Models\MonetizationPackage;
use App\Models\User;
use App\Models\UserVerification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserVerification>
 */
class UserVerificationFactory extends Factory
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
            'status' => UserVerificationStatus::PendingReview,
            'source' => UserVerificationSource::Request,
            'package_name' => $package?->name ?? 'Green Tick Monthly',
            'package_slug' => $package?->slug ?? 'green_tick_monthly',
            'duration_days' => $package?->duration_days ?? 30,
            'price' => $package?->price ?? '0.00',
            'currency' => $package?->currency ?? 'USD',
            'starts_at' => null,
            'ends_at' => null,
            'order_id' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'review_notes' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => UserVerificationStatus::Active,
            'starts_at' => now(),
            'ends_at' => now()->addDays((int) ($attributes['duration_days'] ?? 30)),
            'reviewed_at' => now(),
        ]);
    }
}
