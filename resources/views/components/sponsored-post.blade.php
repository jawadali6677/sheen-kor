@props(['ad'])

<div {{ $attributes->merge(['class' => 'sk-card']) }}>
    <p class="px-4 pt-3 text-xs font-semibold uppercase tracking-wide text-gray-400">Sponsored</p>
    <x-ad-card :ad="$ad" class="border-0 shadow-none" />
</div>
