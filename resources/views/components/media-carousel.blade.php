@props([
    'slides' => [],
    'href' => null,
    'hero' => false,
])

@php
    $slides = array_values($slides);
    $count = count($slides);
    $heroFrame = 'aspect-[4/3] overflow-hidden bg-forest-950 sm:aspect-[16/10] lg:aspect-[2/1]';
@endphp

@if($hero)
    @if($count === 1)
        @php $slide = $slides[0]; @endphp
        <div class="{{ $heroFrame }}">
            @if($slide['type'] === 'video')
                <video src="{{ $slide['src'] }}" class="media-clip h-full w-full object-cover" controls playsinline preload="metadata">
                    Your browser cannot play this video.
                </video>
            @else
                <img src="{{ $slide['src'] }}" alt="{{ $slide['alt'] }}" class="js-lightbox h-full w-full object-cover" loading="lazy">
            @endif
        </div>
    @elseif($count > 1)
        <div
            {{ $attributes->merge(['class' => 'relative bg-forest-950']) }}
            x-data="mediaCarousel({{ $count }})"
        >
            @foreach($slides as $index => $slide)
                <div x-show="index === {{ $index }}" x-cloak class="{{ $heroFrame }}">
                    @if($slide['type'] === 'video')
                        <video src="{{ $slide['src'] }}" class="media-clip h-full w-full object-cover" controls playsinline preload="metadata">
                            Your browser cannot play this video.
                        </video>
                    @else
                        <img src="{{ $slide['src'] }}" alt="{{ $slide['alt'] }}" class="js-lightbox h-full w-full object-cover" loading="lazy">
                    @endif
                </div>
            @endforeach

            <button
                type="button"
                class="absolute left-3 top-1/2 z-10 -translate-y-1/2 rounded-full bg-white/90 px-3 py-2 text-sm font-semibold text-forest-900 shadow-sm"
                x-on:click.stop="prev()"
                aria-label="Previous media"
            >
                ‹
            </button>
            <button
                type="button"
                class="absolute right-3 top-1/2 z-10 -translate-y-1/2 rounded-full bg-white/90 px-3 py-2 text-sm font-semibold text-forest-900 shadow-sm"
                x-on:click.stop="next()"
                aria-label="Next media"
            >
                ›
            </button>
            <div class="pointer-events-none absolute bottom-3 right-3 rounded-full bg-black/70 px-3 py-1 text-xs font-semibold text-white">
                <span x-text="index + 1"></span> / {{ $count }}
            </div>
        </div>
    @endif
@elseif($count === 1)
    @php $slide = $slides[0]; @endphp
    @if($href && $slide['type'] === 'image')
        <a href="{{ $href }}" class="block bg-gray-900">
            <img src="{{ $slide['src'] }}" alt="{{ $slide['alt'] }}" class="max-h-[32rem] w-full object-cover" loading="lazy">
        </a>
    @elseif($slide['type'] === 'video')
        <video src="{{ $slide['src'] }}" class="media-clip max-h-[32rem] w-full" controls playsinline preload="metadata">
            Your browser cannot play this video.
        </video>
    @else
        <img src="{{ $slide['src'] }}" alt="{{ $slide['alt'] }}" class="js-lightbox max-h-[32rem] w-full object-cover" loading="lazy">
    @endif
@elseif($count > 1)
    <div
        {{ $attributes->merge(['class' => 'relative bg-gray-900']) }}
        x-data="mediaCarousel({{ $count }})"
    >
        @foreach($slides as $index => $slide)
            <div x-show="index === {{ $index }}" x-cloak class="bg-black">
                @if($slide['type'] === 'video')
                    <video src="{{ $slide['src'] }}" class="media-clip max-h-[32rem] w-full" controls playsinline preload="metadata">
                        Your browser cannot play this video.
                    </video>
                @else
                    <img src="{{ $slide['src'] }}" alt="{{ $slide['alt'] }}" class="js-lightbox max-h-[32rem] w-full object-cover" loading="lazy">
                @endif
            </div>
        @endforeach

        <button
            type="button"
            class="absolute left-3 top-1/2 z-10 -translate-y-1/2 rounded-full bg-white/90 px-3 py-2 text-sm font-semibold text-forest-900 shadow-sm"
            x-on:click.stop="prev()"
            aria-label="Previous media"
        >
            ‹
        </button>
        <button
            type="button"
            class="absolute right-3 top-1/2 z-10 -translate-y-1/2 rounded-full bg-white/90 px-3 py-2 text-sm font-semibold text-forest-900 shadow-sm"
            x-on:click.stop="next()"
            aria-label="Next media"
        >
            ›
        </button>
        <div class="pointer-events-none absolute bottom-3 right-3 rounded-full bg-black/70 px-3 py-1 text-xs font-semibold text-white">
            <span x-text="index + 1"></span> / {{ $count }}
        </div>
    </div>
@endif
