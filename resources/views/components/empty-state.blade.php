@props([
    'title',
    'actionLabel' => null,
    'actionUrl' => null,
])

<div {{ $attributes->merge(['class' => 'sk-card px-8 py-16 text-center']) }}>
    <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-forest-50 text-forest-700">
        <x-application-logo class="h-6 w-6" />
    </div>
    <h2 class="text-lg font-semibold text-forest-900">{{ $title }}</h2>
    <p class="mx-auto mt-2 max-w-md text-sm text-gray-500">{{ $slot }}</p>
    @if($actionLabel && $actionUrl)
        <a href="{{ $actionUrl }}" class="btn-primary mt-6">{{ $actionLabel }}</a>
    @endif
</div>
