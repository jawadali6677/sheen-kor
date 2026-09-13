<x-app-layout>
    <div class="mx-auto max-w-5xl space-y-6">
        <x-flash />

        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold text-forest-900">My Market</h1>
                <p class="mt-1 text-sm text-gray-500">Manage your Green Market listings.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('market.index') }}" class="btn-secondary">Browse Market</a>
                @can('create', App\Models\MarketListing::class)
                    <a href="{{ route('market.create') }}" class="btn-primary">Create listing</a>
                @endcan
            </div>
        </div>

        <div class="flex flex-wrap gap-2 text-sm">
            @foreach(['all' => 'All', 'published' => 'Published', 'pending' => 'Pending', 'rejected' => 'Rejected', 'closed' => 'Closed'] as $value => $label)
                <a
                    href="{{ route('market.mine', ['status' => $value]) }}"
                    class="rounded-full px-3 py-1 font-medium {{ $filter === $value ? 'bg-forest-800 text-white' : 'bg-white text-gray-600 ring-1 ring-gray-200' }}"
                >{{ $label }}</a>
            @endforeach
        </div>

        @if($listings->isEmpty())
            <div class="sk-card p-8 text-center text-gray-600">
                No listings in this filter yet.
            </div>
        @else
            <div class="space-y-3">
                @foreach($listings as $listing)
                    <article class="sk-card flex flex-col gap-4 p-4 sm:flex-row sm:items-center">
                        @if($listing->featured_image)
                            <a href="{{ route('market.show', $listing) }}" class="shrink-0">
                                <img src="{{ asset('storage/'.$listing->featured_image) }}" alt="{{ $listing->title }}" class="h-24 w-32 rounded-xl object-cover">
                            </a>
                        @endif
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <a href="{{ route('market.show', $listing) }}" class="font-semibold text-forest-900 hover:underline">{{ $listing->title }}</a>
                                <x-market-status-badge :listing="$listing" />
                            </div>
                            <p class="mt-1 text-sm text-gray-500">
                                {{ $listing->listing_type->label() }}
                                @if($listing->category)
                                    · {{ $listing->category->name }}
                                @endif
                                · {{ $listing->location_name }}
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @can('update', $listing)
                                <a href="{{ route('market.edit', $listing) }}" class="btn-secondary text-sm">Edit</a>
                            @endcan
                            @can('markSold', $listing)
                                <form method="POST" action="{{ route('market.sold', $listing) }}">
                                    @csrf
                                    <button type="submit" class="btn-secondary text-sm">Mark sold</button>
                                </form>
                            @endcan
                            @can('markExchanged', $listing)
                                <form method="POST" action="{{ route('market.exchanged', $listing) }}">
                                    @csrf
                                    <button type="submit" class="btn-secondary text-sm">Mark exchanged</button>
                                </form>
                            @endcan
                            @can('markDonated', $listing)
                                <form method="POST" action="{{ route('market.donated', $listing) }}">
                                    @csrf
                                    <button type="submit" class="btn-secondary text-sm">Mark donated</button>
                                </form>
                            @endcan
                            @can('close', $listing)
                                <form method="POST" action="{{ route('market.close', $listing) }}">
                                    @csrf
                                    <button type="submit" class="btn-secondary text-sm">Close</button>
                                </form>
                            @endcan
                            @can('delete', $listing)
                                <form
                                    method="POST"
                                    action="{{ route('market.destroy', $listing) }}"
                                    data-confirm="Delete this listing?"
                                    data-confirm-message="This action cannot be undone."
                                    data-confirm-action="Delete"
                                >
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn-secondary text-sm text-red-700">Delete</button>
                                </form>
                            @endcan
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="mt-6">
                {{ $listings->links() }}
            </div>
        @endif
    </div>
</x-app-layout>
