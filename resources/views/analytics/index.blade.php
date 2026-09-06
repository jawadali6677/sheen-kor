<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Analytics
            </h2>
            <div class="flex gap-2">
                @foreach(['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly'] as $value => $label)
                    <a
                        href="{{ route('analytics.index', ['period' => $value]) }}"
                        class="px-3 py-1.5 rounded text-sm {{ $report['period'] === $value ? 'bg-gray-800 text-white' : 'bg-white text-gray-700 border' }}"
                    >
                        {{ $label }}
                    </a>
                @endforeach
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <p class="text-sm text-gray-500">{{ $report['range_label'] }}</p>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-white rounded-lg shadow-sm p-4">
                    <p class="text-sm text-gray-500">Users</p>
                    <p class="text-2xl font-semibold">{{ number_format($report['totals']['users']) }}</p>
                    <p class="text-xs text-gray-500">
                        {{ number_format($report['totals']['users_active']) }} active
                        · {{ number_format($report['totals']['users_disabled']) }} disabled
                        · +{{ number_format($report['totals']['users_period']) }} in this range
                    </p>
                </div>
                <div class="bg-white rounded-lg shadow-sm p-4">
                    <p class="text-sm text-gray-500">Stories</p>
                    <p class="text-2xl font-semibold">{{ number_format($report['totals']['stories']) }}</p>
                    <p class="text-xs text-gray-500">+{{ number_format($report['totals']['stories_period']) }} in this range</p>
                </div>
                <div class="bg-white rounded-lg shadow-sm p-4">
                    <p class="text-sm text-gray-500">Alerts</p>
                    <p class="text-2xl font-semibold">{{ number_format($report['totals']['alerts']) }}</p>
                    <p class="text-xs text-gray-500">+{{ number_format($report['totals']['alerts_period']) }} in this range</p>
                </div>
                <div class="bg-white rounded-lg shadow-sm p-4">
                    <p class="text-sm text-gray-500">Alert status</p>
                    <p class="text-sm mt-2">
                        <span class="text-amber-600 font-semibold">{{ $report['totals']['alerts_open'] }} open</span>
                        · <span class="text-blue-600 font-semibold">{{ $report['totals']['alerts_in_progress'] }} in progress</span>
                        · <span class="text-green-600 font-semibold">{{ $report['totals']['alerts_fixed'] }} fixed</span>
                    </p>
                    <p class="text-xs text-gray-500 mt-1">{{ number_format($report['totals']['fixes_period']) }} fixed in this range</p>
                </div>
            </div>

            <div class="grid md:grid-cols-2 gap-6">
                <div class="bg-white rounded-lg shadow-sm p-4">
                    <h3 class="font-semibold mb-2">Activity</h3>
                    <div id="activity-chart"></div>
                </div>
                <div class="bg-white rounded-lg shadow-sm p-4">
                    <h3 class="font-semibold mb-2">Alert workflow</h3>
                    <div id="workflow-chart"></div>
                </div>
                <div class="bg-white rounded-lg shadow-sm p-4">
                    <h3 class="font-semibold mb-2">Alerts by status</h3>
                    <div id="alert-status-chart"></div>
                </div>
                <div class="bg-white rounded-lg shadow-sm p-4">
                    <h3 class="font-semibold mb-2">Alerts by severity</h3>
                    <div id="alert-severity-chart"></div>
                </div>
                <div class="bg-white rounded-lg shadow-sm p-4">
                    <h3 class="font-semibold mb-2">Stories by status</h3>
                    <div id="story-status-chart"></div>
                </div>
                <div class="bg-white rounded-lg shadow-sm p-4">
                    <h3 class="font-semibold mb-2">Users by role</h3>
                    <div id="user-role-chart"></div>
                </div>
                <div class="bg-white rounded-lg shadow-sm p-4">
                    <h3 class="font-semibold mb-2">Users by account status</h3>
                    <div id="user-status-chart"></div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script>window.analyticsReport = @json($report);</script>
    <script src="{{ asset('js/analytics-charts.js') }}"></script>
</x-app-layout>
