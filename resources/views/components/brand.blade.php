@props([
    'theme' => 'dark',
])

@php
    $textClass = $theme === 'light' ? 'text-white' : 'text-forest-800';
    $iconClass = $theme === 'light' ? 'text-lime-400' : 'text-forest-700';
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2']) }}>
    <x-application-logo class="h-8 w-8 {{ $iconClass }}" />
    <span class="text-lg font-bold tracking-tight {{ $textClass }}">SHEEN KOR</span>
</span>
