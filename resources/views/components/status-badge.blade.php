@props(['alert'])

@php
    $map = [
        'open' => ['Open', 'bg-amber-50 text-amber-800 ring-amber-200'],
        'in_progress' => ['In progress', 'bg-sky-50 text-sky-800 ring-sky-200'],
        'fixed' => ['Resolved', 'bg-forest-50 text-forest-800 ring-forest-200'],
    ];
    [$label, $classes] = $map[$alert->status] ?? ['Open', 'bg-gray-100 text-gray-700 ring-gray-200'];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset '.$classes]) }}>
    {{ $label }}
</span>
