<?php

namespace App\Reports;

use App\Models\Alert;
use App\Models\Post;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class AnalyticsReport
{
    /**
     * @return array{
     *     period: string,
     *     range_label: string,
     *     labels: list<string>,
     *     activity: array{users: list<int>, stories: list<int>, alerts: list<int>},
     *     workflow: array{claimed: list<int>, fixed: list<int>},
     *     totals: array<string, int>,
     *     alerts_by_status: array<string, int>,
     *     alerts_by_severity: array<string, int>,
     *     stories_by_status: array<string, int>,
     *     users_by_role: array<string, int>,
     *     users_by_status: array<string, int>
     * }
     */
    public function build(string $period): array
    {
        $period = in_array($period, ['daily', 'weekly', 'monthly'], true) ? $period : 'daily';

        [$start, $end, $bucketKeys, $labels] = $this->buckets($period);

        return [
            'period' => $period,
            'range_label' => $start->toFormattedDateString().' – '.$end->toFormattedDateString(),
            'labels' => $labels,
            'activity' => [
                'users' => $this->series(User::query()->pluck('created_at'), $bucketKeys, $period, $start, $end),
                'stories' => $this->series(Post::query()->pluck('created_at'), $bucketKeys, $period, $start, $end),
                'alerts' => $this->series(Alert::query()->pluck('created_at'), $bucketKeys, $period, $start, $end),
            ],
            'workflow' => [
                'claimed' => $this->series(Alert::query()->whereNotNull('action_taken_at')->pluck('action_taken_at'), $bucketKeys, $period, $start, $end),
                'fixed' => $this->series(Alert::query()->whereNotNull('fixed_at')->pluck('fixed_at'), $bucketKeys, $period, $start, $end),
            ],
            'totals' => [
                'users' => User::query()->count(),
                'users_period' => User::query()->whereBetween('created_at', [$start, $end])->count(),
                'stories' => Post::query()->count(),
                'stories_period' => Post::query()->whereBetween('created_at', [$start, $end])->count(),
                'alerts' => Alert::query()->count(),
                'alerts_period' => Alert::query()->whereBetween('created_at', [$start, $end])->count(),
                'alerts_open' => Alert::query()->where('status', 'open')->count(),
                'alerts_in_progress' => Alert::query()->where('status', 'in_progress')->count(),
                'alerts_fixed' => Alert::query()->where('status', 'fixed')->count(),
                'fixes_period' => Alert::query()->whereBetween('fixed_at', [$start, $end])->count(),
                'users_active' => User::query()->where('status', true)->count(),
                'users_disabled' => User::query()->where('status', false)->count(),
            ],
            'alerts_by_status' => [
                'Open' => Alert::query()->where('status', 'open')->count(),
                'In progress' => Alert::query()->where('status', 'in_progress')->count(),
                'Fixed' => Alert::query()->where('status', 'fixed')->count(),
            ],
            'alerts_by_severity' => [
                'Low' => Alert::query()->where('severity', 'low')->count(),
                'Medium' => Alert::query()->where('severity', 'medium')->count(),
                'High' => Alert::query()->where('severity', 'high')->count(),
            ],
            'stories_by_status' => [
                'Published' => Post::query()->where('status', 'published')->count(),
                'Pending' => Post::query()->where('status', 'pending')->count(),
            ],
            'users_by_role' => [
                'User' => User::query()->where('role', 'user')->count(),
                'Moderator' => User::query()->where('role', 'moderator')->count(),
                'Admin' => User::query()->where('role', 'admin')->count(),
            ],
            'users_by_status' => [
                'Active' => User::query()->where('status', true)->count(),
                'Disabled' => User::query()->where('status', false)->count(),
            ],
        ];
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: list<string>, 3: list<string>}
     */
    private function buckets(string $period): array
    {
        $end = CarbonImmutable::now()->endOfDay();

        if ($period === 'monthly') {
            $start = CarbonImmutable::now()->startOfMonth()->subMonths(11);
            $cursor = $start;
            $keys = [];
            $labels = [];

            while ($cursor->lte($end)) {
                $keys[] = $cursor->format('Y-m');
                $labels[] = $cursor->format('M Y');
                $cursor = $cursor->addMonth();
            }

            return [$start, $end, $keys, $labels];
        }

        if ($period === 'weekly') {
            $start = CarbonImmutable::now()->startOfWeek()->subWeeks(11);
            $cursor = $start;
            $keys = [];
            $labels = [];

            while ($cursor->lte($end)) {
                $keys[] = $cursor->format('o-\WW');
                $labels[] = 'Week '.$cursor->format('W Y');
                $cursor = $cursor->addWeek();
            }

            return [$start, $end, $keys, $labels];
        }

        $start = CarbonImmutable::now()->subDays(13)->startOfDay();
        $cursor = $start;
        $keys = [];
        $labels = [];

        while ($cursor->lte($end)) {
            $keys[] = $cursor->format('Y-m-d');
            $labels[] = $cursor->format('M j');
            $cursor = $cursor->addDay();
        }

        return [$start, $end, $keys, $labels];
    }

    /**
     * @param  Collection<int, mixed>  $timestamps
     * @param  list<string>  $bucketKeys
     * @return list<int>
     */
    private function series(Collection $timestamps, array $bucketKeys, string $period, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $counts = array_fill_keys($bucketKeys, 0);

        foreach ($timestamps as $timestamp) {
            if ($timestamp === null) {
                continue;
            }

            $date = CarbonImmutable::parse($timestamp);

            if ($date->lt($start) || $date->gt($end)) {
                continue;
            }

            $key = match ($period) {
                'monthly' => $date->format('Y-m'),
                'weekly' => $date->copy()->startOfWeek()->format('o-\WW'),
                default => $date->format('Y-m-d'),
            };

            if (array_key_exists($key, $counts)) {
                $counts[$key]++;
            }
        }

        return array_values($counts);
    }
}
