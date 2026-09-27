@props([
    'iconClass' => 'h-12 w-12',
])

<div {{ $attributes->merge(['class' => 'flex h-full w-full items-center justify-center bg-gradient-to-br from-forest-600 via-forest-800 to-forest-950']) }} role="img" aria-label="{{ __('No photo') }}">
    <svg class="{{ $iconClass }} text-lime-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 21V10" />
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 13c0-3.4 2.6-5.6 6.5-6.2-.8 3.6-2.8 5.4-6.5 6.2Z" />
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5c0-2.6-2.2-4.4-5.4-4.8.7 2.8 2.6 4.2 5.4 4.8Z" />
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 21c0-3.2 1.6-5.2 4-6.4" />
    </svg>
</div>
