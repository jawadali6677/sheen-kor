@props([
    'ad',
    'compact' => false,
])

<article {{ $attributes->merge(['class' => 'sk-card overflow-hidden']) }} x-data="{ hidden: false }" x-show="!hidden" x-cloak>
    <div class="flex items-center justify-between px-4 pt-3">
        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Sponsored</p>
        <button type="button" class="rounded-md p-1 text-gray-400 hover:bg-gray-50 hover:text-gray-600" @click="hidden = true" aria-label="Hide advertisement">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
    @if(! $compact && ! empty($ad['image']))
        <img src="{{ $ad['image'] }}" alt="{{ $ad['title'] }}" class="mt-2 h-36 w-full object-cover" loading="lazy">
    @endif
    <div class="space-y-1 px-4 py-3">
        <p class="text-xs font-medium text-forest-700">{{ $ad['advertiser'] }}</p>
        <h3 class="text-sm font-semibold text-forest-900">{{ $ad['title'] }}</h3>
        <p class="text-sm text-gray-500">{{ $ad['description'] }}</p>
        <a href="{{ $ad['url'] }}" class="mt-2 inline-flex text-sm font-semibold text-forest-800 hover:text-forest-600">
            {{ $ad['cta'] }}
        </a>
    </div>
</article>
