@props([
    'user',
    'size' => 'md',
    'lightbox' => false,
])

@php
    $classes = match ($size) {
        'sm' => 'feed-avatar',
        'lg' => 'profile-avatar profile-avatar-lg',
        default => 'profile-avatar',
    };
@endphp

@if($user->avatarUrl())
    <img
        src="{{ $user->avatarUrl() }}"
        alt="{{ $user->name }}"
        {{ $attributes->merge(['class' => $classes.' object-cover'.($lightbox ? ' js-lightbox' : '')]) }}
    >
@else
    <div {{ $attributes->merge(['class' => $classes]) }}>{{ $user->initials() }}</div>
@endif
