<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Orders</h2>
            <div class="flex flex-wrap gap-3">
                <a href="{{ route('admin.monetization.dashboard') }}" class="text-sm text-blue-700">Dashboard</a>
                <a href="{{ route('admin.monetization.index') }}" class="text-sm text-blue-700">Monetization settings</a>
            </div>
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

            <div class="grid gap-3 sm:grid-cols-3">
                <div class="rounded-lg bg-white p-4 shadow-sm">
                    <p class="text-xs uppercase tracking-wide text-gray-500">Pending</p>
                    <p class="mt-1 text-2xl font-semibold text-gray-900">{{ $counts['pending'] }}</p>
                </div>
                <div class="rounded-lg bg-white p-4 shadow-sm">
                    <p class="text-xs uppercase tracking-wide text-gray-500">Orders today</p>
                    <p class="mt-1 text-2xl font-semibold text-gray-900">{{ $counts['today'] }}</p>
                </div>
                <div class="rounded-lg bg-white p-4 shadow-sm">
                    <p class="text-xs uppercase tracking-wide text-gray-500">Paid today</p>
                    <p class="mt-1 text-2xl font-semibold text-gray-900">{{ $counts['paid_today'] }}</p>
                </div>
            </div>

            <div class="flex flex-wrap gap-2 text-sm">
                @foreach(['pending' => 'Pending', 'paid' => 'Paid', 'failed' => 'Failed', 'cancelled' => 'Cancelled', 'all' => 'All'] as $value => $label)
                    <a href="{{ route('admin.monetization.orders.index', ['status' => $value, 'q' => $search ?: null]) }}" class="rounded-full px-3 py-1 {{ $status === $value ? 'bg-forest-800 text-white' : 'bg-white text-gray-700 ring-1 ring-gray-200' }}">{{ $label }}</a>
                @endforeach
            </div>

            <form method="GET" action="{{ route('admin.monetization.orders.index') }}" class="flex flex-wrap gap-2">
                <input type="hidden" name="status" value="{{ $status }}">
                <input type="search" name="q" value="{{ $search }}" placeholder="Search by ID, name, or email" class="rounded-md border-gray-300 text-sm">
                <button type="submit" class="rounded bg-gray-800 px-3 py-2 text-sm text-white">Search</button>
            </form>

            <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-left">
                        <tr>
                            <th class="px-4 py-3">Order</th>
                            <th class="px-4 py-3">Member</th>
                            <th class="px-4 py-3">Purchase</th>
                            <th class="px-4 py-3">Payment</th>
                            <th class="px-4 py-3">Benefit</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($orders as $order)
                            <tr class="border-t">
                                <td class="px-4 py-3 whitespace-nowrap">
                                    #{{ $order->id }}
                                    <span class="block text-gray-500">{{ $order->created_at?->toFormattedDateString() }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <p class="font-medium">{{ $order->user?->name ?? 'Unknown' }}</p>
                                    <p class="text-gray-500">{{ $order->user?->email }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    <p class="font-medium">{{ $order->purchaseTypeLabel() }}</p>
                                    @include('admin.monetization.orders.partials.subject', ['order' => $order])
                                    <p class="text-gray-500">{{ $order->snapshot['name'] ?? $order->package?->name }} · {{ $order->amount }} {{ $order->currency }}</p>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">{{ $order->status->label() }}</td>
                                <td class="px-4 py-3">
                                    <p>{{ $order->benefitStatusLabel() }}</p>
                                    @if($order->promotionPlacementLabel())
                                        <p class="text-gray-500">{{ $order->promotionPlacementLabel() }}</p>
                                    @endif
                                    @if($order->resultDetail())
                                        <p class="text-gray-500">{{ $order->resultDetail() }}</p>
                                    @endif
                                    @if($order->benefitStartsAt() || $order->benefitEndsAt())
                                        <p class="text-gray-500">{{ $order->benefitStartsAt()?->toFormattedDateString() ?? 'Not started' }} – {{ $order->benefitEndsAt()?->toFormattedDateString() ?? 'No end date' }}</p>
                                    @elseif($duration = $order->durationLabel())
                                        <p class="text-gray-500">{{ $duration }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('admin.monetization.orders.show', $order) }}" class="text-blue-700">Inspect</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-6 text-gray-500">No orders in this list.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div>{{ $orders->links() }}</div>
        </div>
    </div>
</x-app-layout>
