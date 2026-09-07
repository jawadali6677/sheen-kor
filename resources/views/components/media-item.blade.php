@props([
    'media',
    'alt' => '',
])

@if($media->isVideo())
    <video
        {{ $attributes->merge([
            'class' => 'media-clip',
            'controls' => true,
            'playsinline' => true,
            'preload' => 'metadata',
        ]) }}
        src="{{ asset('storage/'.$media->image) }}"
    >
        Your browser cannot play this video.
    </video>
@else
    <img
        {{ $attributes->merge(['class' => 'js-lightbox']) }}
        src="{{ asset('storage/'.$media->image) }}"
        alt="{{ $alt }}"
    >
@endif
