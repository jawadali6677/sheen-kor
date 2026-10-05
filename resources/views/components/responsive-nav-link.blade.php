@props(['active'])

@php
$classes = ($active ?? false)
            ? 'block w-full border-l-4 border-lime-400 bg-emerald-50 py-2 pe-4 ps-3 text-start text-base font-medium text-forest-900 transition duration-150 ease-in-out focus:border-forest-700 focus:bg-emerald-100 focus:text-forest-900 focus:outline-none'
            : 'block w-full border-l-4 border-transparent py-2 pe-4 ps-3 text-start text-base font-medium text-gray-600 transition duration-150 ease-in-out hover:border-forest-200 hover:bg-sand-50 hover:text-forest-800 focus:border-forest-200 focus:bg-sand-50 focus:text-forest-800 focus:outline-none';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
