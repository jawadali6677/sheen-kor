@props(['value'])

<label {{ $attributes->merge(['class' => 'sk-label']) }}>
    {{ $value ?? $slot }}
</label>
