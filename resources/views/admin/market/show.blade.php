<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ $listing->title }}</h2>
            <a href="{{ route('admin.market.index', ['status' => in_array($listing->status->value, ['published', 'rejected'], true) ? $listing->status->value : 'pending']) }}" class="text-sm text-forest-800 hover:underline">Back to market</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="mb-6 rounded bg-green-100 p-4 text-green-700">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="mb-6 rounded bg-red-100 p-4 text-red-700">{{ session('error') }}</div>
            @endif

            <article class="bg-white p-6 shadow-sm sm:rounded-lg">
                <p class="text-sm text-gray-500">
                    #{{ $listing->id }}
                    · {{ $listing->user?->name ?? 'Unknown' }}
                    · {{ $listing->listing_type->label() }}
                    · {{ $listing->status->label() }}
                    · Created {{ $listing->created_at?->format('M d, Y') }}
                    @if($listing->published_at)
                        · Published {{ $listing->published_at->format('M d, Y') }}
                    @endif
                </p>

                @if($listing->hasMedia())
                    <div class="mt-4">
                        <x-media-carousel :slides="$listing->mediaSlides()" />
                    </div>
                @endif

                <p class="mt-4 font-semibold text-forest-900">{{ $listing->listing_type->catalogOfferLabel($listing->price) }}</p>
                <p class="mt-2 text-sm text-gray-500">{{ $listing->category?->name }} · {{ $listing->condition->label() }}</p>
                <p class="mt-2 text-sm text-gray-500">{{ $listing->location_name }}</p>

                <div class="mt-4 whitespace-pre-line text-gray-800">{{ $listing->description }}</div>

                @if($listing->exchange_details)
                    <p class="mt-4 text-gray-700"><span class="font-semibold">In exchange for:</span> {{ $listing->exchange_details }}</p>
                @endif
            </article>

            <section class="mt-6 bg-white p-6 shadow-sm sm:rounded-lg">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h3 class="text-lg font-semibold text-forest-900">Reports</h3>
                    @if($listing->reports->contains(fn ($report) => $report->status === 'pending'))
                        <form method="POST" action="{{ route('admin.market.reports.review', $listing) }}">
                            @csrf
                            <button type="submit" class="rounded-md bg-white px-3 py-1.5 text-sm font-medium text-forest-800 ring-1 ring-gray-200">Mark reports reviewed</button>
                        </form>
                    @endif
                </div>

                @forelse($listing->reports as $report)
                    <div class="mt-4 border-t border-gray-100 pt-4 text-sm">
                        <p class="font-medium text-gray-800">{{ $report->user?->name ?? 'Unknown' }} · {{ $report->reason->label() }} · {{ $report->status }}</p>
                        @if($report->details)
                            <p class="mt-1 text-gray-600">{{ $report->details }}</p>
                        @endif
                    </div>
                @empty
                    <p class="mt-3 text-sm text-gray-500">No reports on this listing.</p>
                @endforelse
            </section>

            <div class="mt-6 flex flex-wrap gap-2">
                @if($listing->status->value !== 'published')
                    <form method="POST" action="{{ route('admin.market.publish', $listing) }}">
                        @csrf
                        <button type="submit" class="rounded-md bg-forest-800 px-4 py-2 text-sm text-white">Publish</button>
                    </form>
                @endif
                @if($listing->status->value !== 'pending')
                    <form method="POST" action="{{ route('admin.market.pending', $listing) }}">
                        @csrf
                        <button type="submit" class="rounded-md bg-amber-600 px-4 py-2 text-sm text-white">Set pending</button>
                    </form>
                @endif
                @if($listing->status->value !== 'rejected')
                    <form method="POST" action="{{ route('admin.market.reject', $listing) }}">
                        @csrf
                        <button type="submit" class="rounded-md bg-gray-800 px-4 py-2 text-sm text-white">Reject</button>
                    </form>
                @endif
                <form method="POST" action="{{ route('admin.market.destroy', $listing) }}" data-confirm="Delete this listing?" data-confirm-message="This action cannot be undone." data-confirm-action="Delete">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="rounded-md bg-red-600 px-4 py-2 text-sm text-white">Delete</button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
