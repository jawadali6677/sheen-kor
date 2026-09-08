@forelse($alerts as $index => $alert)
    <x-alert-card :alert="$alert" />
    @if((($alerts->firstItem() ?? 1) + $index) % 5 === 0)
        <x-in-feed-ad :ad="demo_ads()[($index) % count(demo_ads())]" />
    @endif
@empty
    @unless(request()->boolean('partial') || request()->hasHeader('X-Infinite-Scroll'))
        <x-empty-state title="No alerts yet" :action-label="auth()->check() ? 'Report an alert' : 'Join Sheen Kor'" :action-url="auth()->check() ? route('alerts.create') : route('register')">
            Report dumping, pollution, or other environmental harm so the community can respond.
        </x-empty-state>
    @endunless
@endforelse
<div data-infinite-next="{{ $alerts->hasMorePages() ? $alerts->nextPageUrl() : '' }}" hidden></div>
