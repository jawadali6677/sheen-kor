<?php

namespace App\Http\Controllers;

use App\Actions\CompleteRewardedAd;
use App\Actions\StartRewardedAd;
use App\Models\RewardedAdSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class RewardedAdController extends Controller
{
    public function store(Request $request, StartRewardedAd $startRewardedAd): RedirectResponse
    {
        try {
            $startRewardedAd->handle($request->user());
        } catch (RuntimeException $exception) {
            return back()->with('error', $this->messageFor($exception->getMessage()));
        }

        return back()->with('success', 'Rewarded session started. A video ad provider is not connected yet, so no reward can be granted.');
    }

    public function complete(Request $request, RewardedAdSession $rewardedAdSession, CompleteRewardedAd $completeRewardedAd): RedirectResponse
    {
        $validated = $request->validate([
            'provider_reference' => ['nullable', 'string', 'max:191'],
            'reward_type' => ['nullable', 'string'],
            'reward_value' => ['nullable'],
        ]);

        try {
            $completeRewardedAd->handle(
                $request->user(),
                $rewardedAdSession,
                $validated['provider_reference'] ?? null,
            );
        } catch (RuntimeException $exception) {
            return back()->with('error', $this->messageFor($exception->getMessage()));
        }

        return back()->with('success', 'Reward recorded. It does not change environmental scores or Feed ranking.');
    }

    public function fail(Request $request, RewardedAdSession $rewardedAdSession, CompleteRewardedAd $completeRewardedAd): RedirectResponse
    {
        try {
            $completeRewardedAd->fail($request->user(), $rewardedAdSession);
        } catch (RuntimeException $exception) {
            return back()->with('error', $this->messageFor($exception->getMessage()));
        }

        return back()->with('success', 'The rewarded session was closed without a reward.');
    }

    private function messageFor(string $reason): string
    {
        return match ($reason) {
            'disabled' => 'Rewarded videos are turned off.',
            'daily_limit' => 'You have already used today’s rewarded video limit.',
            'cooldown' => 'Please wait before starting another rewarded video.',
            'already_started' => 'You already have an open rewarded session.',
            'no_inventory' => 'No rewarded advertisements are available.',
            'forbidden' => 'You cannot change another member’s rewarded session.',
            'not_started' => 'That rewarded session cannot be completed.',
            'unverified' => 'Completion was rejected because no ad provider verified it. This is placeholder behavior until a provider is connected.',
            'replay' => 'That completion has already been used.',
            default => 'The rewarded video request could not be processed.',
        };
    }
}
