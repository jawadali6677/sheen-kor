<?php

namespace App\Actions;

use App\Enums\AdEventType;
use App\Enums\AdPlacement;
use App\Enums\RewardedAdStatus;
use App\Enums\RewardType;
use App\Models\AdEvent;
use App\Models\Advertisement;
use App\Models\RewardedAdSession;
use App\Models\User;
use Illuminate\Support\Str;
use RuntimeException;

class StartRewardedAd
{
    public function handle(User $user): RewardedAdSession
    {
        if (! monetization_setting('rewarded_ads_enabled', false)) {
            throw new RuntimeException('disabled');
        }

        $dailyLimit = (int) monetization_setting('rewarded_daily_limit', 1);

        if ($dailyLimit <= 0 || $this->completedToday($user) >= $dailyLimit) {
            throw new RuntimeException('daily_limit');
        }

        $cooldownMinutes = (int) monetization_setting('rewarded_cooldown_minutes', 1440);

        if ($cooldownMinutes > 0) {
            $lastCompleted = RewardedAdSession::query()
                ->where('user_id', $user->id)
                ->where('status', RewardedAdStatus::Completed)
                ->latest('completed_at')
                ->first();

            if ($lastCompleted?->completed_at !== null && $lastCompleted->completed_at->gt(now()->subMinutes($cooldownMinutes))) {
                throw new RuntimeException('cooldown');
            }
        }

        $open = RewardedAdSession::query()
            ->where('user_id', $user->id)
            ->where('status', RewardedAdStatus::Started)
            ->exists();

        if ($open) {
            throw new RuntimeException('already_started');
        }

        $advertisement = Advertisement::query()
            ->enabled()
            ->where('is_rewarded', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();

        if ($advertisement === null) {
            throw new RuntimeException('no_inventory');
        }

        $rewardType = RewardType::tryFrom((string) monetization_setting('rewarded_type', RewardType::ProfileVisibilityCredit->value))
            ?? RewardType::ProfileVisibilityCredit;
        $rewardValue = max(0, (int) monetization_setting('rewarded_value', 1));

        $session = RewardedAdSession::query()->create([
            'user_id' => $user->id,
            'advertisement_id' => $advertisement->id,
            'status' => RewardedAdStatus::Started,
            'reward_type' => $rewardType,
            'reward_value' => $rewardValue,
            'completion_token' => (string) Str::uuid(),
            'started_at' => now(),
            'notes' => 'Placeholder session. No ad provider is connected, so completion cannot be verified yet.',
        ]);

        AdEvent::query()->create([
            'advertisement_id' => $advertisement->id,
            'user_id' => $user->id,
            'visitor_key' => hash('sha256', 'user:'.$user->id),
            'type' => AdEventType::RewardedStart,
            'placement' => AdPlacement::Rewarded,
        ]);

        return $session;
    }

    private function completedToday(User $user): int
    {
        return RewardedAdSession::query()
            ->where('user_id', $user->id)
            ->where('status', RewardedAdStatus::Completed)
            ->whereDate('completed_at', now()->toDateString())
            ->count();
    }
}
