<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Monetization dashboard</h2>
            <div class="flex flex-wrap gap-2">
                @foreach(['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly'] as $value => $label)
                    <a
                        href="{{ route('admin.monetization.dashboard', ['period' => $value]) }}"
                        class="rounded px-3 py-1.5 text-sm {{ $report['period'] === $value ? 'bg-gray-800 text-white' : 'bg-white text-gray-700 border' }}"
                    >{{ $label }}</a>
                @endforeach
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
            <p class="text-sm text-gray-500">{{ $report['range_label'] }} · Amounts in {{ $report['currency'] }} from marked-paid orders only.</p>
            <p>
                <a href="{{ route('admin.monetization.index') }}" class="text-sm text-blue-700">Monetization settings</a>
                <span class="text-gray-400"> · </span>
                <a href="{{ route('admin.monetization.orders.index') }}" class="text-sm text-blue-700">Orders</a>
            </p>

            <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                <div class="rounded-lg bg-white p-4 shadow-sm">
                    <p class="text-sm text-gray-500">Paid revenue</p>
                    <p class="text-2xl font-semibold">{{ $report['totals']['paid_revenue'] }} {{ $report['currency'] }}</p>
                    <p class="text-xs text-gray-500">{{ $report['totals']['paid_revenue_period'] }} in this range · {{ $report['totals']['paid_orders'] }} paid orders</p>
                </div>
                <div class="rounded-lg bg-white p-4 shadow-sm">
                    <p class="text-sm text-gray-500">Pending orders</p>
                    <p class="text-2xl font-semibold">{{ number_format($report['totals']['pending_orders']) }}</p>
                    <p class="text-xs text-gray-500">{{ number_format($report['totals']['orders_period']) }} created in this range</p>
                </div>
                <div class="rounded-lg bg-white p-4 shadow-sm">
                    <p class="text-sm text-gray-500">Active entitlements</p>
                    <p class="text-sm mt-2">
                        <span class="font-semibold">{{ $report['totals']['active_green_ticks'] }} Green Ticks</span>
                        · {{ $report['totals']['active_boosts'] }} active boosts
                        · {{ $report['totals']['active_listing_promotions'] }} listing promotions
                    </p>
                    <p class="mt-1 text-xs text-gray-500">{{ $report['totals']['pending_green_tick_reviews'] }} Green Tick awaiting review</p>
                </div>
                <div class="rounded-lg bg-white p-4 shadow-sm">
                    <p class="text-sm text-gray-500">Ad events</p>
                    <p class="text-2xl font-semibold">{{ number_format($report['totals']['impressions']) }} impressions</p>
                    <p class="text-xs text-gray-500">{{ number_format($report['totals']['clicks']) }} clicks · {{ number_format($report['totals']['impressions_period']) }} / {{ number_format($report['totals']['clicks_period']) }} in this range</p>
                </div>
            </div>

            <div class="grid gap-6 md:grid-cols-2">
                <div class="rounded-lg bg-white p-4 shadow-sm">
                    <h3 class="mb-2 font-semibold">Paid revenue</h3>
                    <div id="revenue-chart"></div>
                </div>
                <div class="rounded-lg bg-white p-4 shadow-sm">
                    <h3 class="mb-2 font-semibold">Ads in range</h3>
                    <div id="ads-series-chart"></div>
                </div>
                <div class="rounded-lg bg-white p-4 shadow-sm">
                    <h3 class="mb-2 font-semibold">Paid revenue by package type</h3>
                    <div id="revenue-type-chart"></div>
                </div>
                <div class="rounded-lg bg-white p-4 shadow-sm">
                    <h3 class="mb-2 font-semibold">Orders by status</h3>
                    <div id="orders-status-chart"></div>
                </div>
                <div class="rounded-lg bg-white p-4 shadow-sm">
                    <h3 class="mb-2 font-semibold">Impressions vs clicks</h3>
                    <div id="ads-mix-chart"></div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script>window.monetizationReport = @json($report);</script>
    <script src="{{ asset('js/monetization-charts.js') }}"></script>
</x-app-layout>
