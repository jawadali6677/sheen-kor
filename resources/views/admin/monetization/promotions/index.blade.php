<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Listing promotions</h2>
            <a href="{{ route('admin.monetization.index') }}" class="text-sm text-blue-700">Monetization settings</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-6xl space-y-8 sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="rounded bg-green-100 p-4 text-green-700">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="rounded bg-red-100 p-4 text-red-700">{{ session('error') }}</div>
            @endif

            <div class="flex flex-wrap gap-2 text-sm">
                @foreach(['pending' => 'Pending', 'active' => 'Active', 'expired' => 'Expired', 'cancelled' => 'Cancelled', 'all' => 'All'] as $value => $label)
                    <a href="{{ route('admin.monetization.promotions.index', ['status' => $value]) }}" class="rounded-full px-3 py-1 {{ $status === $value ? 'bg-forest-800 text-white' : 'bg-white text-gray-700 ring-1 ring-gray-200' }}">{{ $label }}</a>
                @endforeach
            </div>

            @if($unpaidGrantsEnabled)
                <section class="rounded-lg bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-800">Unpaid grant</h3>
                    <p class="mt-1 text-sm text-gray-600">Activate a listing promotion without payment while this setting is enabled. No payment record is created.</p>
                    <form method="POST" action="{{ route('admin.monetization.promotions.grant') }}" class="mt-4 grid gap-3 sm:grid-cols-3 sm:items-end">
                        @csrf
                        <div>
                            <label for="listing_id" class="block text-sm font-medium text-gray-700">Listing ID</label>
                            <input id="listing_id" type="number" name="listing_id" min="1" class="mt-1 block w-full rounded-md border-gray-300 text-sm" required>
                        </div>
                        <div>
                            <label for="package_id" class="block text-sm font-medium text-gray-700">Package</label>
                            <select id="package_id" name="package_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm" required>
                                @foreach($packages as $package)
                                    <option value="{{ $package->id }}">{{ $package->name }} · {{ $package->duration_days }} days</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit" class="rounded bg-gray-800 px-4 py-2 text-sm text-white">Grant promotion</button>
                    </form>
                </section>
            @endif

            <div class="overflow-hidden rounded-lg bg-white shadow-sm">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-left">
                        <tr>
                            <th class="px-4 py-3">Member / listing</th>
                            <th class="px-4 py-3">Package</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Dates</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($promotions as $promotion)
                            <tr class="border-t">
                                <td class="px-4 py-3">
                                    <p class="font-medium">{{ $promotion->user?->name ?? 'Unknown' }}</p>
                                    <p class="text-gray-500">{{ $promotion->listing?->title ?? 'Deleted listing' }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    {{ $promotion->package_name }}
                                    <span class="block text-gray-500">{{ $promotion->placement->label() }} · {{ $promotion->price }} {{ $promotion->currency }} · {{ $promotion->duration_days }} days</span>
                                </td>
                                <td class="px-4 py-3">{{ $promotion->displayStatus()->label() }}</td>
                                <td class="px-4 py-3 text-gray-500">
                                    @if($promotion->starts_at)
                                        {{ $promotion->starts_at->toFormattedDateString() }} – {{ $promotion->ends_at?->toFormattedDateString() }}
                                    @else
                                        Not started
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('admin.monetization.promotions.show', $promotion) }}" class="text-blue-700">Inspect</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-6 text-gray-500">No listing promotions in this list.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div>{{ $promotions->links() }}</div>
        </div>
    </div>
</x-app-layout>
