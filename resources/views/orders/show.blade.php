<x-app-layout>
    <div class="mx-auto max-w-3xl space-y-6">
        <x-flash />

        <article class="sk-card p-6">
            <p class="text-sm text-gray-500">Order #{{ $order->id }}</p>
            <h1 class="mt-1 text-2xl font-bold text-forest-900">{{ $order->purchasedPackageName() }}</h1>
            @if($order->status->value === 'paid')
                <p class="mt-3 text-sm font-semibold text-forest-800">Payment received.</p>
            @endif
            <p class="mt-3 text-base font-medium text-forest-900">{{ $order->resultHeadline() }}</p>
            @if($order->resultDetail())
                <p class="mt-1 text-sm text-gray-700">{{ $order->resultDetail() }}.</p>
            @endif
            <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-gray-500">Purchase</dt>
                    <dd class="font-medium text-gray-900">{{ $order->purchaseTypeLabel() }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Item</dt>
                    <dd class="font-medium text-gray-900">{{ $order->purchasedItemName() }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Amount</dt>
                    <dd class="font-medium text-gray-900">{{ $order->amount }} {{ $order->currency }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Payment</dt>
                    <dd class="font-medium text-gray-900">{{ $order->status->label() }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Benefit</dt>
                    <dd class="font-medium text-gray-900">{{ $order->benefitStatusLabel() }}</dd>
                </div>
                @if($order->promotionPlacementLabel())
                    <div>
                        <dt class="text-gray-500">Promotion</dt>
                        <dd class="font-medium text-gray-900">{{ $order->promotionPlacementLabel() }}</dd>
                    </div>
                @endif
                @if($duration = $order->durationLabel())
                    <div>
                        <dt class="text-gray-500">Duration</dt>
                        <dd class="font-medium text-gray-900">{{ $duration }}</dd>
                    </div>
                @endif
                @if($order->benefitStartsAt())
                    <div>
                        <dt class="text-gray-500">Starts</dt>
                        <dd class="font-medium text-gray-900">{{ $order->benefitStartsAt()->toFormattedDateString() }}</dd>
                    </div>
                @endif
                @if($order->benefitEndsAt())
                    <div>
                        <dt class="text-gray-500">Ends</dt>
                        <dd class="font-medium text-gray-900">{{ $order->benefitEndsAt()->toFormattedDateString() }}</dd>
                    </div>
                @endif
            </dl>
            @if($order->status->value === 'pending' && ($checkoutReturn ?? null) === 'awaiting_webhook')
                <p class="mt-1 text-sm text-gray-600">Stripe has not confirmed this payment yet. If checkout already succeeded, the order is marked paid when the webhook arrives. You do not need to pay again.</p>
            @elseif($order->status->value === 'pending' && ($checkoutReturn ?? null) === 'unpaid')
                <p class="mt-1 text-sm text-gray-600">This checkout is not paid yet. You can try paying again.</p>
            @elseif($order->status->value === 'pending' && ($checkoutReturn ?? null) === 'session_mismatch')
                <p class="mt-1 text-sm text-gray-600">That Checkout session belongs to a different order, so this payment was not applied here.</p>
            @elseif($order->status->value === 'pending' && ($checkoutReturn ?? null) === 'session_not_linked')
                <p class="mt-1 text-sm text-gray-600">That Checkout session is not linked to this order, so this payment was not applied here.</p>
            @elseif($order->status->value === 'pending' && request('checkout') === 'cancelled')
                <p class="mt-1 text-sm text-gray-600">Checkout was cancelled. No payment was taken. This order is still pending, so you can try paying again or cancel the reservation.</p>
            @elseif($order->status->value === 'pending' && $order->shouldChargeWithStripe())
                <p class="mt-1 text-sm text-gray-600">Pay with Stripe to activate this purchase. Pending means reserved and waiting for payment — it is not paid yet.</p>
            @elseif($order->status->value === 'pending')
                <p class="mt-1 text-sm text-gray-600">Payment is marked paid by an admin until a payment provider is connected. The purchased benefit stays pending until that happens.</p>
            @endif
        </article>

        @if($order->status->value === 'paid')
            <div class="sk-card flex flex-wrap items-center gap-3 p-6">
                @if($exit = $order->primaryExit())
                    <a href="{{ $exit['url'] }}" class="btn-primary">{{ $exit['label'] }}</a>
                @endif
                @if($order->isListingPromotion())
                    <a href="{{ route('market.mine') }}" class="btn-secondary">My Listings</a>
                    <a href="{{ route('market.index') }}" class="btn-secondary">Back to Market</a>
                @endif
                <a href="{{ route('orders.index') }}" class="btn-secondary">My Orders</a>
            </div>
        @elseif($order->status->value === 'pending')
            <div class="sk-card space-y-4 p-6">
                @if($order->shouldChargeWithStripe() && ($checkoutReturn ?? null) !== 'awaiting_webhook')
                    <form method="POST" action="{{ route('orders.pay', $order) }}">
                        @csrf
                        <button type="submit" class="rounded-lg bg-forest-800 px-4 py-2 text-sm font-medium text-white">Pay with Stripe</button>
                    </form>
                @endif
                <form method="POST" action="{{ route('orders.destroy', $order) }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="text-sm text-red-600">Cancel pending order</button>
                </form>
                <a href="{{ route('orders.index') }}" class="inline-block text-sm font-medium text-forest-800 hover:underline">My Orders</a>
            </div>
        @else
            <div class="flex flex-wrap gap-3">
                <a href="{{ route('orders.index') }}" class="btn-secondary">My Orders</a>
            </div>
        @endif
    </div>
</x-app-layout>
