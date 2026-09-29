@props([
    'ad',
    'sidebar' => false,
])

<x-ad-card :ad="$ad" :sidebar="$sidebar" {{ $attributes }} />
