<x-app-layout>
    <div class="mx-auto max-w-xl space-y-4">
        <x-flash />

        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-xl font-semibold text-forest-900">Environmental Alerts</h1>
            @auth
                <a href="{{ route('alerts.create') }}" class="btn-primary">Report Alert</a>
            @endauth
        </div>

        <form method="GET" action="{{ route('alerts.index') }}" class="sk-card grid gap-3 p-4 sm:grid-cols-[1fr_auto_auto] sm:items-end">
            <div>
                <label for="alert-search" class="text-xs font-semibold uppercase tracking-wide text-gray-400">Search alerts</label>
                <input id="alert-search" type="search" name="q" value="{{ $search }}" class="sk-input" placeholder="Search by title, description, or place...">
            </div>
            <div>
                <label for="alert-status" class="text-xs font-semibold uppercase tracking-wide text-gray-400">Status</label>
                <select id="alert-status" name="status" class="sk-input" onchange="this.form.submit()">
                    <option value="">All statuses</option>
                    <option value="open" @selected($status === 'open')>Open</option>
                    <option value="in_progress" @selected($status === 'in_progress')>In progress</option>
                    <option value="fixed" @selected($status === 'fixed')>Resolved</option>
                </select>
            </div>
            <button type="submit" class="btn-secondary">Search</button>
        </form>

        <div
            x-data="infiniteFeed({
                nextUrl: @js($alerts->nextPageUrl()),
                finishedText: 'No more alerts',
            })"
        >
            <div x-ref="items" class="space-y-4">
                @include('alerts.partials.feed-items')
            </div>
            <div x-ref="sentinel" class="h-8"></div>
            <p class="py-4 text-center text-sm text-gray-500" x-show="loading" x-cloak>Loading...</p>
            <p class="py-4 text-center text-sm text-gray-500" x-show="finished && ! loading && {{ $alerts->total() > 0 ? 'true' : 'false' }}" x-cloak>No more alerts</p>
        </div>
    </div>

    @include('posts.partials.engagement-assets')
</x-app-layout>
