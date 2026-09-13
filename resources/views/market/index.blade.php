<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-6">
        <x-flash />

        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold text-forest-900">Green Market</h1>
                <p class="mt-1 text-sm text-gray-500">Give • Exchange • Reuse</p>
            </div>
            <div class="flex flex-wrap gap-2">
                @auth
                    <a href="{{ route('market.mine') }}" class="btn-secondary">My Market</a>
                @endauth
                @can('create', App\Models\MarketListing::class)
                    <a href="{{ route('market.create') }}" class="btn-primary">Create listing</a>
                @endcan
            </div>
        </div>

        <form method="GET" action="{{ route('market.index') }}" class="sk-card grid gap-3 p-4 md:grid-cols-2 lg:grid-cols-4 lg:items-end">
            <div class="md:col-span-2 lg:col-span-4">
                <label for="market-search" class="text-xs font-semibold uppercase tracking-wide text-gray-400">Search</label>
                <input id="market-search" type="search" name="q" value="{{ $search }}" class="sk-input" placeholder="Search by title or description...">
            </div>
            <div>
                <label for="market-category" class="text-xs font-semibold uppercase tracking-wide text-gray-400">Category</label>
                <select id="market-category" name="category" class="sk-input">
                    <option value="">All categories</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) $categoryId === (string) $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="market-type" class="text-xs font-semibold uppercase tracking-wide text-gray-400">Type</label>
                <select id="market-type" name="type" class="sk-input">
                    <option value="">All types</option>
                    @foreach($types as $listingType)
                        <option value="{{ $listingType->value }}" @selected($type === $listingType->value)>{{ $listingType->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="market-condition" class="text-xs font-semibold uppercase tracking-wide text-gray-400">Condition</label>
                <select id="market-condition" name="condition" class="sk-input">
                    <option value="">All conditions</option>
                    @foreach($conditions as $listingCondition)
                        <option value="{{ $listingCondition->value }}" @selected($condition === $listingCondition->value)>{{ $listingCondition->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="market-location" class="text-xs font-semibold uppercase tracking-wide text-gray-400">Location</label>
                <input id="market-location" type="search" name="location" value="{{ $location }}" class="sk-input" placeholder="City or place name">
            </div>
            <div>
                <label for="market-near-lat" class="text-xs font-semibold uppercase tracking-wide text-gray-400">Nearby latitude</label>
                <input id="market-near-lat" type="text" name="near_lat" value="{{ $nearLat }}" class="sk-input" placeholder="Optional">
            </div>
            <div>
                <label for="market-near-lng" class="text-xs font-semibold uppercase tracking-wide text-gray-400">Nearby longitude</label>
                <input id="market-near-lng" type="text" name="near_lng" value="{{ $nearLng }}" class="sk-input" placeholder="Optional">
            </div>
            <div>
                <label for="market-radius" class="text-xs font-semibold uppercase tracking-wide text-gray-400">Radius (km)</label>
                <input id="market-radius" type="number" name="radius_km" value="{{ $radiusKm }}" min="1" class="sk-input">
            </div>
            <div>
                <button type="submit" class="btn-secondary w-full">Filter</button>
            </div>
        </form>

        @if($listings->isEmpty())
            <div class="sk-card p-8 text-center">
                <p class="text-gray-600">No published listings match these filters yet.</p>
                @can('create', App\Models\MarketListing::class)
                    <a href="{{ route('market.create') }}" class="btn-primary mt-4 inline-flex">Create listing</a>
                @endcan
            </div>
        @else
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @foreach($listings as $listing)
                    <x-market-listing-card :listing="$listing" />
                @endforeach
            </div>

            <div class="mt-6">
                {{ $listings->links() }}
            </div>
        @endif
    </div>
</x-app-layout>
