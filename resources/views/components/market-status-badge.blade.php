@props(['listing'])

@php
    $map = [
        'pending' => ['Pending', 'bg-amber-50 text-amber-800 ring-amber-200'],
        'published' => ['Published', 'bg-forest-50 text-forest-800 ring-forest-200'],
        'rejected' => ['Rejected', 'bg-red-50 text-red-800 ring-red-200'],
        'sold' => ['Sold', 'bg-gray-100 text-gray-700 ring-gray-200'],
        'exchanged' => ['Exchanged', 'bg-sky-50 text-sky-800 ring-sky-200'],
        'donated' => ['Donated', 'bg-forest-50 text-forest-800 ring-forest-200'],
        'closed' => ['Closed', 'bg-gray-100 text-gray-700 ring-gray-200'],
    ];
    [$label, $classes] = $map[$listing->status->value] ?? ['Pending', 'bg-gray-100 text-gray-700 ring-gray-200'];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset '.$classes]) }}>
    {{ $label }}
</span>
