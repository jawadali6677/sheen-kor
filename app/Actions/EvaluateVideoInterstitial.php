<?php

namespace App\Actions;

use App\Enums\AdEventType;
use App\Enums\AdPlacement;
use App\Models\AdEvent;
use App\Models\Advertisement;
use Illuminate\Http\Request;

class EvaluateVideoInterstitial
{
    /**
     * @return array{allowed: bool, reason: string, advertisement: ?array<string, mixed>}
     */
    public function handle(Request $request, string $sourceType, int $sourceId, int $durationSeconds): array
    {
        if (! monetization_setting('video_ads_enabled', false)) {
            return $this->deny('disabled');
        }

        $minimumDuration = max(1, (int) monetization_setting('video_interstitial_min_seconds', 15));

        if ($durationSeconds < $minimumDuration) {
            return $this->deny('duration');
        }

        $sourceKey = $sourceType.':'.$sourceId;
        $shownSources = $request->session()->get('video_interstitial_sources', []);

        if (in_array($sourceKey, $shownSources, true)) {
            return $this->deny('duplicate_source');
        }

        if (! $this->capsAllow($request)) {
            return $this->deny('capped');
        }

        $advertisement = Advertisement::query()
            ->enabled()
            ->where('is_video', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();

        if ($advertisement === null) {
            return $this->deny('no_inventory');
        }

        $this->remember($request, $sourceKey);

        return [
            'allowed' => true,
            'reason' => 'ok',
            'advertisement' => $advertisement->toCard(AdPlacement::VideoInterstitial->value),
        ];
    }

    /**
     * @return array{allowed: bool, reason: string, advertisement: null}
     */
    private function deny(string $reason): array
    {
        return [
            'allowed' => false,
            'reason' => $reason,
            'advertisement' => null,
        ];
    }

    private function capsAllow(Request $request): bool
    {
        $sessionCap = (int) monetization_setting('video_interstitial_session_cap', 2);
        $dailyCap = (int) monetization_setting('video_interstitial_daily_cap', 6);
        $cooldown = (int) monetization_setting('video_interstitial_cooldown_seconds', 300);

        if ($sessionCap > 0 && (int) $request->session()->get('video_interstitial_session_count', 0) >= $sessionCap) {
            return false;
        }

        if ($dailyCap > 0 && $this->dailyCount($request) >= $dailyCap) {
            return false;
        }

        if ($cooldown > 0) {
            $lastVideo = $request->session()->get('video_interstitial_last_at');
            $lastAny = $request->session()->get('ad_last_any_at');
            $lastAt = max((int) $lastVideo, (int) $lastAny);

            if ($lastAt > 0 && (now()->timestamp - $lastAt) < $cooldown) {
                return false;
            }
        }

        return true;
    }

    private function dailyCount(Request $request): int
    {
        $today = now()->toDateString();
        $visitorKey = app(PlaceFeedAds::class)->visitorKey($request);

        $placedToday = (int) $request->session()->get('video_interstitial_daily_count', 0);

        if ($request->session()->get('video_interstitial_daily_date') !== $today) {
            $placedToday = 0;
        }

        $recorded = AdEvent::query()
            ->where('type', AdEventType::Impression)
            ->where('placement', AdPlacement::VideoInterstitial)
            ->whereDate('created_at', $today)
            ->where(function ($query) use ($request, $visitorKey): void {
                $query->where('visitor_key', $visitorKey);

                if ($request->user()) {
                    $query->orWhere('user_id', $request->user()->id);
                }
            })
            ->count();

        return max($placedToday, $recorded);
    }

    private function remember(Request $request, string $sourceKey): void
    {
        $today = now()->toDateString();

        if ($request->session()->get('video_interstitial_daily_date') !== $today) {
            $request->session()->put('video_interstitial_daily_date', $today);
            $request->session()->put('video_interstitial_daily_count', 0);
        }

        $sources = $request->session()->get('video_interstitial_sources', []);
        $sources[] = $sourceKey;

        $request->session()->put('video_interstitial_sources', array_values(array_unique($sources)));
        $request->session()->increment('video_interstitial_session_count');
        $request->session()->increment('video_interstitial_daily_count');
        $request->session()->put('video_interstitial_last_at', now()->timestamp);
        $request->session()->put('ad_last_any_at', now()->timestamp);
    }
}
