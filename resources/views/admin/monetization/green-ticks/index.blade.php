<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Green Tick requests</h2>
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
                @foreach(['pending_review' => 'Pending', 'active' => 'Active', 'rejected' => 'Rejected', 'expired' => 'Expired', 'cancelled' => 'Cancelled', 'all' => 'All'] as $value => $label)
                    <a href="{{ route('admin.monetization.green-ticks.index', ['status' => $value]) }}" class="rounded-full px-3 py-1 {{ $status === $value ? 'bg-forest-800 text-white' : 'bg-white text-gray-700 ring-1 ring-gray-200' }}">{{ $label }}</a>
                @endforeach
            </div>

            @if($unpaidGrantsEnabled)
                <section class="rounded-lg bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-800">Unpaid grant</h3>
                    <p class="mt-1 text-sm text-gray-600">Grant Green Tick without payment while this setting is enabled.</p>
                    <form method="POST" action="{{ route('admin.monetization.green-ticks.grant') }}" class="mt-4 grid gap-3 sm:grid-cols-3 sm:items-end">
                        @csrf
                        <div>
                            <label for="user_id" class="block text-sm font-medium text-gray-700">User ID</label>
                            <input id="user_id" type="number" name="user_id" min="1" class="mt-1 block w-full rounded-md border-gray-300 text-sm" required>
                        </div>
                        <div>
                            <label for="package_id" class="block text-sm font-medium text-gray-700">Package</label>
                            <select id="package_id" name="package_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm" required>
                                @foreach($packages as $package)
                                    <option value="{{ $package->id }}">{{ $package->name }} · {{ $package->duration_days }} days</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit" class="rounded bg-gray-800 px-4 py-2 text-sm text-white">Grant Green Tick</button>
                    </form>
                </section>
            @endif

            <div class="overflow-hidden rounded-lg bg-white shadow-sm">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-left">
                        <tr>
                            <th class="px-4 py-3">Member</th>
                            <th class="px-4 py-3">Package</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Requested</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($requests as $verification)
                            <tr class="border-t">
                                <td class="px-4 py-3">
                                    <p class="font-medium">{{ $verification->user?->name ?? 'Unknown' }}</p>
                                    <p class="text-gray-500">{{ $verification->user?->email }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    {{ $verification->package_name }}
                                    <span class="block text-gray-500">{{ $verification->price }} {{ $verification->currency }}</span>
                                </td>
                                <td class="px-4 py-3">{{ $verification->displayStatus()->label() }}</td>
                                <td class="px-4 py-3">{{ $verification->created_at?->diffForHumans() }}</td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('admin.monetization.green-ticks.show', $verification) }}" class="text-blue-700">Review</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-6 text-gray-500">No Green Tick requests in this list.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div>{{ $requests->links() }}</div>
        </div>
    </div>
</x-app-layout>
