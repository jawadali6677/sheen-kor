@props(['listing'])

<article class="sk-card overflow-hidden">
    <a href="{{ route('market.show', $listing) }}" class="block">
        @if($listing->featured_image)
            <img
                src="{{ asset('storage/'.$listing->featured_image) }}"
                alt="{{ $listing->title }}"
                class="h-48 w-full object-cover"
            >
        @else
            <div class="flex h-48 items-center justify-center bg-sand-50 px-4 text-center text-sm font-medium text-forest-800">
                {{ $listing->title }}
            </div>
        @endif
    </a>
    <div class="space-y-2 p-4">
        <div class="flex flex-wrap items-center gap-2">
            <span class="inline-flex items-center rounded-full bg-forest-50 px-2.5 py-1 text-xs font-semibold text-forest-800 ring-1 ring-inset ring-forest-200">
                {{ $listing->listing_type->label() }}
            </span>
            <span class="text-sm font-semibold text-forest-900">
                {{ $listing->listing_type->catalogOfferLabel($listing->price) }}
            </span>
        </div>
        <h3 class="text-base font-semibold text-forest-900">
            <a href="{{ route('market.show', $listing) }}" class="hover:underline">{{ $listing->title }}</a>
        </h3>
        <p class="text-sm text-gray-500">
            {{ $listing->location_name }}
            ·
            {{ ($listing->published_at ?? $listing->created_at)?->diffForHumans() }}
        </p>
        @if($listing->category)
            <p class="text-xs text-gray-400">{{ $listing->category->name }}</p>
        @endif
    </div>
</article>
