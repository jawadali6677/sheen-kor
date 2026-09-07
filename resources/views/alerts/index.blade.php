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

        @forelse($alerts as $index => $alert)
            <x-alert-card :alert="$alert" />
            @if((($alerts->firstItem() ?? 1) + $index) % 5 === 0)
                <x-in-feed-ad :ad="demo_ads()[($index) % count(demo_ads())]" />
            @endif
        @empty
            <x-empty-state title="No alerts yet" :action-label="auth()->check() ? 'Report an alert' : 'Join Sheen Kor'" :action-url="auth()->check() ? route('alerts.create') : route('register')">
                Report dumping, pollution, or other environmental harm so the community can respond.
            </x-empty-state>
        @endforelse

        <div>{{ $alerts->links() }}</div>
    </div>

    @include('posts.partials.engagement-assets')
</x-app-layout>
