@props([
    'user',
    'size' => 'md',
    'lightbox' => false,
])

@php
    $classes = match ($size) {
        'sm' => 'h-9 w-9 text-xs',
        'lg' => 'profile-avatar profile-avatar-lg h-[7.5rem] w-[7.5rem] text-3xl',
        default => 'profile-avatar h-12 w-12 text-sm',
    };
@endphp

@if($user->avatarUrl())
    <img
        src="{{ $user->avatarUrl() }}"
        alt="{{ $user->name }}"
        {{ $attributes->merge(['class' => $classes.' rounded-full object-cover bg-forest-100'.($lightbox ? ' js-lightbox' : '')]) }}
    >
@else
    <div {{ $attributes->merge(['class' => $classes.' inline-flex items-center justify-center rounded-full bg-forest-800 text-white font-semibold']) }}>{{ $user->initials() }}</div>
@endif
