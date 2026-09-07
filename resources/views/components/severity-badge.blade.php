@props(['severity'])

@php
    $map = [
        'low' => ['Low', 'bg-forest-50 text-forest-800 ring-forest-200'],
        'medium' => ['Medium', 'bg-orange-50 text-orange-800 ring-orange-200'],
        'high' => ['High', 'bg-red-50 text-red-800 ring-red-200'],
    ];
    [$label, $classes] = $map[$severity] ?? [ucfirst((string) $severity), 'bg-gray-100 text-gray-700 ring-gray-200'];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset '.$classes]) }}>
    {{ $label }}
</span>
