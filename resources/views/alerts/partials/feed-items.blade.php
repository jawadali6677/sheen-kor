@php
    $feedAds = feed_ads_for($alerts, 'feed_alerts');
@endphp
@forelse($alerts as $index => $alert)
    <x-alert-card :alert="$alert" />
    @if(isset($feedAds[$index]))
        <x-in-feed-ad :ad="$feedAds[$index]" />
    @endif
@empty
    @unless(request()->boolean('partial') || request()->hasHeader('X-Infinite-Scroll'))
        <x-empty-state title="No alerts yet" :action-label="auth()->check() ? 'Report an alert' : 'Join Sheen Kor'" :action-url="auth()->check() ? route('alerts.create') : route('register')">
            Report dumping, pollution, or other environmental harm so the community can respond.
        </x-empty-state>
    @endunless
@endforelse
<div data-infinite-next="{{ $alerts->hasMorePages() ? $alerts->nextPageUrl() : '' }}" hidden></div>
