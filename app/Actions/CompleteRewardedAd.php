<?php

namespace App\Actions;

use App\Contracts\RewardedAdVerifier;
use App\Enums\AdEventType;
use App\Enums\AdPlacement;
use App\Enums\RewardedAdStatus;
use App\Models\AdEvent;
use App\Models\RewardedAdSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CompleteRewardedAd
{
    public function __construct(private RewardedAdVerifier $verifier) {}

    public function handle(User $user, RewardedAdSession $session, ?string $providerReference): RewardedAdSession
    {
        if ($session->user_id !== $user->id) {
            throw new RuntimeException('forbidden');
        }

        if ($session->status !== RewardedAdStatus::Started) {
            throw new RuntimeException('not_started');
        }

        if (! $this->verifier->verify($session, $providerReference)) {
            throw new RuntimeException('unverified');
        }

        if (! filled($providerReference)) {
            throw new RuntimeException('unverified');
        }

        return DB::transaction(function () use ($user, $session, $providerReference): RewardedAdSession {
            $locked = RewardedAdSession::query()
                ->whereKey($session->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== RewardedAdStatus::Started) {
                throw new RuntimeException('not_started');
            }

            if (RewardedAdSession::query()->where('provider_reference', $providerReference)->exists()) {
                throw new RuntimeException('replay');
            }

            $locked->forceFill([
                'status' => RewardedAdStatus::Completed,
                'provider_reference' => $providerReference,
                'completed_at' => now(),
            ])->save();

            AdEvent::query()->create([
                'advertisement_id' => $locked->advertisement_id,
                'user_id' => $user->id,
                'visitor_key' => hash('sha256', 'user:'.$user->id),
                'type' => AdEventType::RewardedComplete,
                'placement' => AdPlacement::Rewarded,
            ]);

            return $locked->fresh();
        });
    }

    public function fail(User $user, RewardedAdSession $session): RewardedAdSession
    {
        if ($session->user_id !== $user->id) {
            throw new RuntimeException('forbidden');
        }

        if ($session->status !== RewardedAdStatus::Started) {
            throw new RuntimeException('not_started');
        }

        $session->forceFill([
            'status' => RewardedAdStatus::Failed,
            'failed_at' => now(),
        ])->save();

        AdEvent::query()->create([
            'advertisement_id' => $session->advertisement_id,
            'user_id' => $user->id,
            'visitor_key' => hash('sha256', 'user:'.$user->id),
            'type' => AdEventType::RewardedFail,
            'placement' => AdPlacement::Rewarded,
        ]);

        return $session->fresh();
    }
}
