<x-app-layout>
    <div class="mx-auto max-w-3xl space-y-6">
        <x-flash />

        <div>
            <h1 class="text-2xl font-semibold text-forest-900">My Orders</h1>
            <p class="mt-1 text-sm text-gray-500">Promotions, post boosts, and Green Tick purchases.</p>
        </div>

        @if($orders->isEmpty())
            <div class="sk-card p-8 text-center text-gray-600">
                You have no orders yet.
            </div>
        @else
            <div class="space-y-3">
                @foreach($orders as $order)
                    <article class="sk-card p-4">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0">
                                <p class="text-sm text-gray-500">Order #{{ $order->id }} · {{ $order->created_at?->toFormattedDateString() }}</p>
                                <p class="mt-1 text-sm text-gray-600">{{ $order->purchaseTypeLabel() }}</p>
                                <a href="{{ route('orders.show', $order) }}" class="mt-1 block font-semibold text-forest-900 hover:underline">{{ $order->purchasedItemName() }}</a>
                                <p class="mt-1 text-sm text-gray-600">{{ $order->purchasedPackageName() }}</p>
                            </div>
                            <div class="text-sm sm:text-right">
                                <p class="font-medium text-forest-900">{{ $order->amount }} {{ $order->currency }}</p>
                                <p class="mt-1 text-gray-600">{{ $order->status->label() }}</p>
                                <a href="{{ route('orders.show', $order) }}" class="mt-2 inline-block font-medium text-forest-800 hover:underline">View order</a>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>

            <div>{{ $orders->links() }}</div>
        @endif
    </div>
</x-app-layout>
