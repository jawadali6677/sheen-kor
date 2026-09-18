<?php

namespace App\Reports;

use App\Enums\AdEventType;
use App\Enums\MonetizationPackageType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserVerificationStatus;
use App\Models\AdEvent;
use App\Models\ListingPromotion;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PostBoost;
use App\Models\UserVerification;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class MonetizationReport
{
    /**
     * @return array{
     *     period: string,
     *     range_label: string,
     *     currency: string,
     *     labels: list<string>,
     *     series: array{revenue: list<float>, paid_orders: list<int>, impressions: list<int>, clicks: list<int>},
     *     totals: array<string, int|string>,
     *     revenue_by_type: array<string, float>,
     *     orders_by_status: array<string, int>,
     *     ads_in_range: array<string, int>
     * }
     */
    public function build(string $period): array
    {
        $period = in_array($period, ['daily', 'weekly', 'monthly'], true) ? $period : 'daily';

        [$start, $end, $bucketKeys, $labels] = $this->buckets($period);

        $paidPayments = Payment::query()
            ->where('status', PaymentStatus::Paid)
            ->with('order:id,amount,snapshot,status')
            ->get(['id', 'order_id', 'amount', 'updated_at']);

        $paidPaymentsInRange = $paidPayments->filter(function (Payment $payment) use ($start, $end): bool {
            $at = $payment->updated_at;

            return $at !== null && $at->gte($start) && $at->lte($end);
        });

        return [
            'period' => $period,
            'range_label' => $start->toFormattedDateString().' – '.$end->toFormattedDateString(),
            'currency' => (string) monetization_setting('currency', 'USD'),
            'labels' => $labels,
            'series' => [
                'revenue' => $this->sumSeries($paidPaymentsInRange, $bucketKeys, $period, $start, $end),
                'paid_orders' => $this->countSeries($paidPaymentsInRange->pluck('updated_at'), $bucketKeys, $period, $start, $end),
                'impressions' => $this->countSeries(
                    AdEvent::query()->where('type', AdEventType::Impression)->pluck('created_at'),
                    $bucketKeys,
                    $period,
                    $start,
                    $end,
                ),
                'clicks' => $this->countSeries(
                    AdEvent::query()->where('type', AdEventType::Click)->pluck('created_at'),
                    $bucketKeys,
                    $period,
                    $start,
                    $end,
                ),
            ],
            'totals' => [
                'pending_orders' => Order::query()->where('status', OrderStatus::Pending)->count(),
                'paid_orders' => Order::query()->where('status', OrderStatus::Paid)->count(),
                'paid_revenue' => $this->formatMoney(Order::query()->where('status', OrderStatus::Paid)->sum('amount')),
                'orders_period' => Order::query()->whereBetween('created_at', [$start, $end])->count(),
                'paid_orders_period' => $paidPaymentsInRange->count(),
                'paid_revenue_period' => $this->formatMoney($paidPaymentsInRange->sum(fn (Payment $payment) => (float) $payment->amount)),
                'active_green_ticks' => UserVerification::query()->currentlyActive()->count(),
                'pending_green_tick_reviews' => UserVerification::query()->where('status', UserVerificationStatus::PendingReview)->count(),
                'active_boosts' => PostBoost::query()->currentlyActive()->count(),
                'active_listing_promotions' => ListingPromotion::query()->currentlyActive()->count(),
                'impressions' => AdEvent::query()->where('type', AdEventType::Impression)->count(),
                'clicks' => AdEvent::query()->where('type', AdEventType::Click)->count(),
                'impressions_period' => AdEvent::query()
                    ->where('type', AdEventType::Impression)
                    ->whereBetween('created_at', [$start, $end])
                    ->count(),
                'clicks_period' => AdEvent::query()
                    ->where('type', AdEventType::Click)
                    ->whereBetween('created_at', [$start, $end])
                    ->count(),
            ],
            'revenue_by_type' => $this->revenueByType($paidPaymentsInRange),
            'orders_by_status' => $this->ordersByStatus(),
            'ads_in_range' => [
                'Impressions' => AdEvent::query()
                    ->where('type', AdEventType::Impression)
                    ->whereBetween('created_at', [$start, $end])
                    ->count(),
                'Clicks' => AdEvent::query()
                    ->where('type', AdEventType::Click)
                    ->whereBetween('created_at', [$start, $end])
                    ->count(),
            ],
        ];
    }

    /**
     * @param  Collection<int, Payment>  $payments
     * @return array<string, float>
     */
    private function revenueByType(Collection $payments): array
    {
        $totals = [];

        foreach (MonetizationPackageType::cases() as $type) {
            if ($type === MonetizationPackageType::RewardedBoost) {
                continue;
            }

            $totals[$type->label()] = 0.0;
        }

        foreach ($payments as $payment) {
            $type = $payment->order?->snapshot['type'] ?? null;
            $label = MonetizationPackageType::tryFrom((string) $type)?->label() ?? 'Other';
            $totals[$label] = ($totals[$label] ?? 0.0) + (float) $payment->amount;
        }

        return $totals;
    }

    /**
     * @return array<string, int>
     */
    private function ordersByStatus(): array
    {
        $counts = Order::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $byStatus = [];

        foreach (OrderStatus::cases() as $status) {
            $byStatus[$status->label()] = (int) ($counts[$status->value] ?? 0);
        }

        return $byStatus;
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
    private function countSeries(Collection $timestamps, array $bucketKeys, string $period, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $counts = array_fill_keys($bucketKeys, 0);

        foreach ($timestamps as $timestamp) {
            $key = $this->bucketKey($timestamp, $period, $start, $end);

            if ($key !== null && array_key_exists($key, $counts)) {
                $counts[$key]++;
            }
        }

        return array_values($counts);
    }

    /**
     * @param  Collection<int, Payment>  $payments
     * @param  list<string>  $bucketKeys
     * @return list<float>
     */
    private function sumSeries(Collection $payments, array $bucketKeys, string $period, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $sums = array_fill_keys($bucketKeys, 0.0);

        foreach ($payments as $payment) {
            $key = $this->bucketKey($payment->updated_at, $period, $start, $end);

            if ($key !== null && array_key_exists($key, $sums)) {
                $sums[$key] += (float) $payment->amount;
            }
        }

        return array_map(fn (float $value): float => round($value, 2), array_values($sums));
    }

    private function bucketKey(mixed $timestamp, string $period, CarbonImmutable $start, CarbonImmutable $end): ?string
    {
        if ($timestamp === null) {
            return null;
        }

        $date = CarbonImmutable::parse($timestamp);

        if ($date->lt($start) || $date->gt($end)) {
            return null;
        }

        return match ($period) {
            'monthly' => $date->format('Y-m'),
            'weekly' => $date->copy()->startOfWeek()->format('o-\WW'),
            default => $date->format('Y-m-d'),
        };
    }

    private function formatMoney(mixed $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
