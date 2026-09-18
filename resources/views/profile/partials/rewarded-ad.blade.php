<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">Optional sponsored video</h2>
        <p class="mt-1 text-sm text-gray-600">
            Watch a short sponsored video to receive a {{ $rewardedType->label() }} ({{ $rewardedValue }}).
            This does not add environmental score or change Feed ranking. Completion is verified on the server; no ad provider is connected yet.
        </p>
    </header>

    @if(! $rewardedAdsEnabled)
        <p class="mt-4 text-sm text-gray-500">Rewarded videos are turned off.</p>
    @elseif($openRewardedSession)
        <div class="mt-4 rounded-lg border border-gray-200 p-4 text-sm">
            <p class="font-medium text-gray-900">A rewarded session is open.</p>
            <p class="mt-1 text-gray-600">{{ $openRewardedSession->notes }}</p>
            <form method="POST" action="{{ route('rewarded-ads.fail', $openRewardedSession) }}" class="mt-3">
                @csrf
                <button type="submit" class="text-sm text-red-600">Close without a reward</button>
            </form>
        </div>
    @elseif($rewardedSession?->status->value === 'completed' && $rewardedSession->completed_at?->isToday())
        <p class="mt-4 text-sm text-gray-600">You already completed a rewarded video today (limit {{ $rewardedDailyLimit }}).</p>
    @else
        <form method="POST" action="{{ route('rewarded-ads.store') }}" class="mt-4">
            @csrf
            <input type="hidden" name="reward_type" value="ignore-client">
            <input type="hidden" name="reward_value" value="999">
            <button type="submit" class="btn-secondary">Watch a short sponsored video to receive a reward</button>
        </form>
    @endif
</section>
