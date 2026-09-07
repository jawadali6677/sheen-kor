@props([
    'name',
    'lat' => null,
    'lng' => null,
    'mapId' => null,
    'readonly' => true,
])

<section {{ $attributes->merge(['class' => 'sk-card p-4']) }}>
    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Location</p>
    <p class="mt-1 text-base font-semibold text-forest-900">{{ $name ?: 'Location not specified' }}</p>

    @if($lat && $lng)
        @include('partials.location-map', [
            'mapId' => $mapId ?? 'location-preview',
            'readonly' => $readonly,
            'lat' => $lat,
            'lng' => $lng,
            'name' => $name,
        ])
        <p class="mt-2 text-xs text-gray-400">
            Coordinates: {{ number_format((float) $lat, 5) }}, {{ number_format((float) $lng, 5) }}
        </p>
    @endif
</section>
