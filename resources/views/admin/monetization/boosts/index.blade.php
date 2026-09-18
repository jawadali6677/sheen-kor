<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Post boosts</h2>
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
                    <a href="{{ route('admin.monetization.boosts.index', ['status' => $value]) }}" class="rounded-full px-3 py-1 {{ $status === $value ? 'bg-forest-800 text-white' : 'bg-white text-gray-700 ring-1 ring-gray-200' }}">{{ $label }}</a>
                @endforeach
            </div>

            @if($unpaidGrantsEnabled)
                <section class="rounded-lg bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-800">Unpaid grant</h3>
                    <p class="mt-1 text-sm text-gray-600">Activate a boost without payment while this setting is enabled. No payment record is created.</p>
                    <form method="POST" action="{{ route('admin.monetization.boosts.grant') }}" class="mt-4 grid gap-3 sm:grid-cols-3 sm:items-end">
                        @csrf
                        <div>
                            <label for="post_id" class="block text-sm font-medium text-gray-700">Post ID</label>
                            <input id="post_id" type="number" name="post_id" min="1" class="mt-1 block w-full rounded-md border-gray-300 text-sm" required>
                        </div>
                        <div>
                            <label for="package_id" class="block text-sm font-medium text-gray-700">Package</label>
                            <select id="package_id" name="package_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm" required>
                                @foreach($packages as $package)
                                    <option value="{{ $package->id }}">{{ $package->name }} · {{ $package->duration_days }} days</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit" class="rounded bg-gray-800 px-4 py-2 text-sm text-white">Grant boost</button>
                    </form>
                </section>
            @endif

            <div class="overflow-hidden rounded-lg bg-white shadow-sm">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-left">
                        <tr>
                            <th class="px-4 py-3">Member / post</th>
                            <th class="px-4 py-3">Package</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Dates</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($boosts as $boost)
                            <tr class="border-t">
                                <td class="px-4 py-3">
                                    <p class="font-medium">{{ $boost->user?->name ?? 'Unknown' }}</p>
                                    <p class="text-gray-500">{{ $boost->post?->title ?? 'Deleted post' }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    {{ $boost->package_name }}
                                    <span class="block text-gray-500">{{ $boost->price }} {{ $boost->currency }} · {{ $boost->duration_days }} days</span>
                                </td>
                                <td class="px-4 py-3">{{ $boost->displayStatus()->label() }}</td>
                                <td class="px-4 py-3 text-gray-500">
                                    @if($boost->starts_at)
                                        {{ $boost->starts_at->toFormattedDateString() }} – {{ $boost->ends_at?->toFormattedDateString() }}
                                    @else
                                        Not started
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('admin.monetization.boosts.show', $boost) }}" class="text-blue-700">Inspect</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-6 text-gray-500">No boosts in this list.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div>{{ $boosts->links() }}</div>
        </div>
    </div>
</x-app-layout>
