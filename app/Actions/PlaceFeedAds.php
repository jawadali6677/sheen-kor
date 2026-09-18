<?php

namespace App\Actions;

use App\Enums\AdPlacement;
use App\Models\AdEvent;
use App\Models\Advertisement;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class PlaceFeedAds
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function handle(LengthAwarePaginator $paginator, AdPlacement $placement, ?Request $request = null): array
    {
        $request ??= request();
        $enabledKey = $placement->feedEnabledSettingKey();

        if ($enabledKey === null || ! monetization_setting($enabledKey, false)) {
            return [];
        }

        if ($placement === AdPlacement::Sidebar) {
            return [];
        }

        $inventory = $this->feedInventory();

        if ($inventory->isEmpty() || $paginator->isEmpty()) {
            return [];
        }

        $firstItem = (int) ($paginator->firstItem() ?? 1);
        $lastItem = (int) ($paginator->lastItem() ?? $firstItem);
        $slots = [];

        foreach ($this->plannedPositions($lastItem) as $slot => $organicPosition) {
            if ($organicPosition < $firstItem || $organicPosition > $lastItem) {
                continue;
            }

            if (! $this->capsAllow($request)) {
                continue;
            }

            $index = $organicPosition - $firstItem;
            $advertisement = $inventory[$slot % $inventory->count()];
            $slots[$index] = $advertisement->toCard($placement->value);
            $this->rememberPlacement($request);
        }

        return $slots;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function sidebarCards(): array
    {
        if (! monetization_setting('sidebar_ads_enabled', false)) {
            return [];
        }

        return Advertisement::query()
            ->enabled()
            ->where('is_sidebar', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (Advertisement $advertisement): array => $advertisement->toCard(AdPlacement::Sidebar->value))
            ->all();
    }

    public function visitorKey(?Request $request = null): string
    {
        $request ??= request();

        if (! $request->hasSession()) {
            $request->session()->start();
        }

        return hash('sha256', $request->session()->getId());
    }

    /**
     * @return Collection<int, Advertisement>
     */
    private function feedInventory(): Collection
    {
        return Advertisement::query()
            ->enabled()
            ->where('is_feed', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->values();
    }

    /**
     * @return array<int, int>
     */
    private function plannedPositions(int $lastOrganicItem): array
    {
        $interval = max(1, (int) monetization_setting('feed_ad_interval', 8));
        $jitter = max(0, (int) monetization_setting('feed_ad_jitter', 0));
        $minimumInterval = max(1, $interval - $jitter);
        $maximumInterval = max($minimumInterval, $interval + $jitter);
        $span = $maximumInterval - $minimumInterval + 1;

        $positions = [];
        $cursor = 0;
        $slot = 0;

        while ($cursor < $lastOrganicItem) {
            $offset = $span === 1 ? 0 : ((int) sprintf('%u', crc32('feed-ad-slot-'.$slot)) % $span);
            $cursor += $minimumInterval + $offset;
            $slot++;

            if ($cursor <= $lastOrganicItem) {
                $positions[$slot - 1] = $cursor;
            }
        }

        return $positions;
    }

    private function capsAllow(Request $request): bool
    {
        $sessionCap = (int) monetization_setting('feed_ad_session_cap', 6);
        $dailyCap = (int) monetization_setting('feed_ad_daily_cap', 20);
        $cooldown = (int) monetization_setting('feed_ad_cooldown_seconds', 60);

        if ($sessionCap > 0 && (int) $request->session()->get('feed_ad_session_count', 0) >= $sessionCap) {
            return false;
        }

        if ($dailyCap > 0 && $this->dailyCount($request) >= $dailyCap) {
            return false;
        }

        if ($cooldown > 0) {
            $lastAt = $request->session()->get('feed_ad_last_placed_at');

            if (is_numeric($lastAt) && (now()->timestamp - (int) $lastAt) < $cooldown) {
                return false;
            }
        }

        return true;
    }

    private function dailyCount(Request $request): int
    {
        $today = now()->toDateString();

        if ($request->session()->get('feed_ad_daily_date') !== $today) {
            $request->session()->put('feed_ad_daily_date', $today);
            $request->session()->put('feed_ad_daily_count', 0);
        }

        $placedToday = (int) $request->session()->get('feed_ad_daily_count', 0);

        $recorded = AdEvent::query()
            ->where('type', 'impression')
            ->whereDate('created_at', $today)
            ->where(function ($query) use ($request): void {
                $query->where('visitor_key', $this->visitorKey($request));

                if ($request->user()) {
                    $query->orWhere('user_id', $request->user()->id);
                }
            })
            ->count();

        return max($placedToday, $recorded);
    }

    private function rememberPlacement(Request $request): void
    {
        $today = now()->toDateString();

        if ($request->session()->get('feed_ad_daily_date') !== $today) {
            $request->session()->put('feed_ad_daily_date', $today);
            $request->session()->put('feed_ad_daily_count', 0);
        }

        $request->session()->increment('feed_ad_session_count');
        $request->session()->increment('feed_ad_daily_count');
        $request->session()->put('feed_ad_last_placed_at', now()->timestamp);
        $request->session()->put('ad_last_any_at', now()->timestamp);
    }
}
