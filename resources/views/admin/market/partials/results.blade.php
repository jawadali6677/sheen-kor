<div
    data-admin-market-meta
    data-status="{{ $status }}"
    data-search="{{ $search }}"
    data-counts='@json($counts)'
></div>

<div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
    <div class="hidden overflow-x-auto md:block">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-left">
                <tr>
                    <th class="px-4 py-3">ID</th>
                    <th class="px-4 py-3">Owner</th>
                    <th class="px-4 py-3">Listing</th>
                    <th class="px-4 py-3">Type</th>
                    <th class="px-4 py-3">Category</th>
                    <th class="px-4 py-3">Price</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Promotion</th>
                    <th class="px-4 py-3">Reports</th>
                    <th class="px-4 py-3">Created</th>
                    <th class="px-4 py-3">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($listings as $listing)
                    <tr class="border-t">
                        <td class="px-4 py-3 text-gray-500">{{ $listing->id }}</td>
                        <td class="px-4 py-3">{{ $listing->user?->name ?? 'Unknown' }}</td>
                        <td class="px-4 py-3">
                            <a href="{{ route('admin.market.show', $listing) }}" class="font-medium text-forest-800 hover:underline">{{ $listing->title }}</a>
                            <p class="mt-1 text-gray-500">{{ \Illuminate\Support\Str::limit(strip_tags((string) $listing->description), 90) }}</p>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $listing->listing_type->label() }}</td>
                        <td class="px-4 py-3">{{ $listing->category?->name ?? '—' }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $listing->listing_type->catalogOfferLabel($listing->price) }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $listing->status->label() }}</td>
                        <td class="px-4 py-3">
                            @include('admin.market.partials.promotion-status', ['listing' => $listing])
                        </td>
                        <td class="px-4 py-3">{{ $listing->pending_reports_count }}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-gray-500">{{ $listing->created_at?->format('M d, Y') }}</td>
                        <td class="px-4 py-3">
                            @include('admin.market.partials.row-actions', ['listing' => $listing])
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="11" class="px-4 py-10 text-center text-gray-500">No listings match this filter.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="space-y-3 p-4 md:hidden">
        @forelse($listings as $listing)
            <div class="rounded-lg border border-gray-100 p-4">
                <p class="text-xs text-gray-500">#{{ $listing->id }} · {{ $listing->user?->name ?? 'Unknown' }}</p>
                <p class="mt-1 font-medium text-forest-900">{{ $listing->title }}</p>
                <p class="mt-1 text-sm text-gray-500">{{ \Illuminate\Support\Str::limit(strip_tags((string) $listing->description), 90) }}</p>
                <p class="mt-2 text-xs text-gray-600">{{ $listing->listing_type->label() }} · {{ $listing->status->label() }} · {{ $listing->pending_reports_count }} reports</p>
                <div class="mt-2 text-xs">
                    @include('admin.market.partials.promotion-status', ['listing' => $listing])
                </div>
                <div class="mt-3">
                    @include('admin.market.partials.row-actions', ['listing' => $listing])
                </div>
            </div>
        @empty
            <p class="py-8 text-center text-gray-500">No listings match this filter.</p>
        @endforelse
    </div>
</div>

<div class="mt-6" data-admin-market-pagination>
    {{ $listings->links() }}
</div>
