@props([
    'sourceType',
    'sourceId',
])

<div
    class="relative"
    x-data="contentVideoAds({
        eligibilityUrl: @js(route('ads.video-interstitials.store')),
        sourceType: @js($sourceType),
        sourceId: {{ (int) $sourceId }},
    })"
>
    {{ $slot }}

    <div
        x-show="open"
        x-cloak
        class="absolute inset-0 z-20 flex items-end justify-center bg-black/50 p-3 sm:items-center"
        role="dialog"
        aria-modal="true"
        aria-label="Sponsored message"
    >
        <div class="max-h-[80%] w-full max-w-md overflow-y-auto rounded-2xl bg-white p-4 shadow-card" @click.stop>
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Sponsored</p>
            <p class="mt-1 text-sm text-gray-600">A short sponsored message after that video. You can continue whenever you are ready.</p>
            <template x-if="advertisement">
                <div class="mt-3">
                    <p class="text-xs font-medium text-forest-700" x-text="advertisement.advertiser"></p>
                    <h3 class="text-sm font-semibold text-forest-900" x-text="advertisement.title"></h3>
                    <p class="mt-1 text-sm text-gray-500" x-text="advertisement.description"></p>
                    <a :href="advertisement.url" rel="nofollow sponsored noopener" class="mt-2 inline-flex text-sm font-semibold text-forest-800" x-text="advertisement.cta"></a>
                </div>
            </template>
            <button type="button" class="btn-primary mt-4 w-full" @click="dismiss()">Continue</button>
        </div>
    </div>
</div>
