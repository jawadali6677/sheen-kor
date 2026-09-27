@props(['listing'])

@php
    $offer = $listing->listing_type->catalogOfferLabel($listing->price);
    $categoryName = $listing->category?->name;
@endphp

<article class="sk-card group flex h-full flex-col overflow-hidden transition duration-200 lg:hover:-translate-y-0.5 lg:hover:shadow-[0_14px_32px_rgba(15,61,46,0.12)]">
    <a href="{{ route('market.show', $listing) }}" class="relative block aspect-[4/3] overflow-hidden bg-forest-900">
        @if($listing->featured_image)
            <img
                src="{{ asset('storage/'.$listing->featured_image) }}"
                alt="{{ $listing->title }}"
                class="h-full w-full object-cover transition duration-300 lg:group-hover:scale-105"
            >
        @else
            <x-market-photo-placeholder />
        @endif

        @if($badge = $listing->promotionBadge())
            <span class="absolute left-2 top-2 z-10 inline-flex max-w-[calc(100%-1rem)] truncate rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-800 shadow-sm sm:left-3 sm:top-3 sm:px-2.5 sm:py-1 sm:text-xs">
                {{ $badge }}
            </span>
        @endif

        <span class="absolute bottom-2 left-2 z-10 max-w-[calc(100%-1rem)] truncate rounded-full bg-white/95 px-2 py-0.5 text-xs font-semibold text-forest-900 shadow-sm sm:bottom-3 sm:left-3 sm:px-2.5 sm:py-1 sm:text-sm">
            {{ $offer }}
        </span>
    </a>
    <div class="flex flex-1 flex-col gap-1 p-3 sm:p-4">
        <p class="text-[11px] font-semibold uppercase tracking-wide text-forest-700 sm:text-xs">{{ $listing->listing_type->label() }}</p>
        <h3 class="line-clamp-2 text-sm font-semibold leading-snug text-forest-900 sm:text-base">
            <a href="{{ route('market.show', $listing) }}" class="hover:underline">{{ $listing->title }}</a>
        </h3>
        <p class="line-clamp-2 text-xs text-gray-500 sm:text-sm">
            {{ $listing->location_name }}
            @if($categoryName)
                <span aria-hidden="true">·</span> {{ $categoryName }}
            @endif
        </p>
    </div>
</article>
