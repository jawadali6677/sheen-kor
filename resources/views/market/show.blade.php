<x-app-layout>
    <div class="mx-auto max-w-5xl space-y-6">
        <x-flash />

        <article class="sk-card overflow-hidden">
            @if($listing->hasMedia())
                <x-media-carousel :slides="$listing->mediaSlides()" />
            @endif
            @if($listing->status->isCompleted())
                <div class="border-b border-amber-100 bg-amber-50 px-6 py-3 text-sm font-medium text-amber-900 md:px-10">
                    This listing is no longer available.
                </div>
            @endif
            <div class="p-6 md:p-10">
                <div class="flex flex-wrap items-center gap-2">
                    <p class="text-sm font-semibold text-forest-700">{{ $listing->listing_type->label() }}</p>
                    <x-market-status-badge :listing="$listing" />
                    @if($listing->promotionBadge())
                        <span class="inline-flex rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-amber-800">{{ $listing->promotionBadge() }}</span>
                    @endif
                </div>
                <h1 class="mt-2 text-3xl font-bold text-forest-900 md:text-4xl">{{ $listing->title }}</h1>
                <p class="mt-4 text-lg font-semibold text-forest-900">{{ $listing->listing_type->catalogOfferLabel($listing->price) }}</p>

                @if($listing->category)
                    <p class="mt-2 text-sm text-gray-500">{{ $listing->category->name }} · {{ $listing->condition->label() }}</p>
                @endif

                <div class="mt-4 flex flex-wrap items-center gap-3 text-sm text-gray-500">
                    @if($listing->user)
                        <a href="{{ route('users.show', $listing->user) }}" class="inline-flex items-center gap-2 font-semibold text-forest-800">
                            <x-user-avatar :user="$listing->user" size="sm" />
                            {{ $listing->user->name }}
                        </a>
                    @endif
                    <span>{{ ($listing->published_at ?? $listing->created_at)?->format('M d, Y') }}</span>
                    <span>{{ $listing->location_name }}</span>
                </div>

                <div class="prose mt-8 max-w-none text-lg leading-8 text-gray-700">{!! nl2br(e($listing->description)) !!}</div>

                @if($listing->exchange_details)
                    <p class="mt-6 text-gray-700"><span class="font-semibold">In exchange for:</span> {{ $listing->exchange_details }}</p>
                @endif

                @if($listing->latitude && $listing->longitude)
                    @include('partials.location-map', [
                        'mapId' => 'market-show-map',
                        'latName' => 'latitude',
                        'lngName' => 'longitude',
                        'nameField' => 'location_name',
                        'readonly' => true,
                        'lat' => $listing->latitude,
                        'lng' => $listing->longitude,
                        'name' => $listing->location_name,
                    ])
                @endif

                <div class="mt-10 flex flex-wrap gap-3 border-t border-gray-100 pt-6">
                    @can('contact', $listing)
                        <form method="POST" action="{{ route('market.contact', $listing) }}">
                            @csrf
                            <button type="submit" class="btn-primary">Contact seller</button>
                        </form>
                    @else
                        @guest
                            @if($listing->status === \App\Enums\MarketListingStatus::Published)
                                <a href="{{ route('login') }}" class="btn-primary">Log in to contact seller</a>
                            @endif
                        @endguest
                    @endcan
                    @can('report', $listing)
                        @if($viewerHasReported)
                            <p class="self-center text-sm text-gray-500">You reported this listing.</p>
                        @else
                            <form method="POST" action="{{ route('market.report', $listing) }}" class="flex flex-wrap items-end gap-2" x-data="{ reason: '{{ old('reason', 'spam') }}' }">
                                @csrf
                                <div>
                                    <label for="report-reason" class="sr-only">Reason</label>
                                    <select id="report-reason" name="reason" x-model="reason" class="rounded-md border-gray-300 text-sm">
                                        @foreach(\App\Enums\MarketListingReportReason::cases() as $reason)
                                            <option value="{{ $reason->value }}">{{ $reason->label() }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div x-show="reason === 'other'" x-cloak>
                                    <label for="report-details" class="sr-only">Details</label>
                                    <input id="report-details" type="text" name="details" value="{{ old('details') }}" placeholder="Tell us more" class="rounded-md border-gray-300 text-sm">
                                </div>
                                <button type="submit" class="btn-secondary">Report listing</button>
                            </form>
                        @endif
                    @else
                        @guest
                            @if($listing->status->isPubliclyVisible())
                                <a href="{{ route('login') }}" class="btn-secondary">Log in to report</a>
                            @endif
                        @endguest
                    @endcan
                    @can('update', $listing)
                        <a href="{{ route('market.edit', $listing) }}" class="btn-primary">Edit listing</a>
                    @endcan
                    @can('promote', $listing)
                        <a href="{{ route('market.promote.create', $listing) }}" class="btn-secondary">Promote listing</a>
                    @endcan
                    @can('markSold', $listing)
                        <form method="POST" action="{{ route('market.sold', $listing) }}">
                            @csrf
                            <button type="submit" class="btn-secondary">Mark sold</button>
                        </form>
                    @endcan
                    @can('markExchanged', $listing)
                        <form method="POST" action="{{ route('market.exchanged', $listing) }}">
                            @csrf
                            <button type="submit" class="btn-secondary">Mark exchanged</button>
                        </form>
                    @endcan
                    @can('markDonated', $listing)
                        <form method="POST" action="{{ route('market.donated', $listing) }}">
                            @csrf
                            <button type="submit" class="btn-secondary">Mark donated</button>
                        </form>
                    @endcan
                    @can('close', $listing)
                        <form method="POST" action="{{ route('market.close', $listing) }}">
                            @csrf
                            <button type="submit" class="btn-secondary">Close</button>
                        </form>
                    @endcan
                    @can('delete', $listing)
                        <form
                            action="{{ route('market.destroy', $listing) }}"
                            method="POST"
                            data-confirm="Delete this listing?"
                            data-confirm-message="This action cannot be undone."
                            data-confirm-action="Delete"
                        >
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-secondary text-red-700">Delete listing</button>
                        </form>
                    @endcan
                    <a href="{{ route('market.index') }}" class="btn-secondary">Back to Market</a>
                </div>
            </div>
        </article>
    </div>
</x-app-layout>
