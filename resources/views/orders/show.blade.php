<x-app-layout>
    <div class="mx-auto max-w-3xl space-y-6">
        <x-flash />

        <article class="sk-card p-6">
            <p class="text-sm text-gray-500">Order #{{ $order->id }}</p>
            <h1 class="mt-1 text-2xl font-bold text-forest-900">{{ $order->snapshot['name'] ?? $order->package?->name }}</h1>
            <p class="mt-2 text-sm text-gray-600">Status: {{ $order->status->label() }} · {{ $order->amount }} {{ $order->currency }}</p>
            @if($order->status->value === 'pending' && request('checkout') === 'success')
                <p class="mt-1 text-sm text-gray-600">Confirming payment with Stripe. This order is still pending and is not paid. The benefit stays reserved until Stripe confirms the payment.</p>
            @elseif($order->status->value === 'pending' && request('checkout') === 'cancelled')
                <p class="mt-1 text-sm text-gray-600">Checkout was cancelled. No payment was taken. This order is still pending, so you can try paying again or cancel the reservation.</p>
            @elseif($order->status->value === 'pending' && $order->shouldChargeWithStripe())
                <p class="mt-1 text-sm text-gray-600">Pay with Stripe to activate this purchase. Pending means reserved and waiting for payment — it is not paid yet.</p>
            @elseif($order->status->value === 'pending')
                <p class="mt-1 text-sm text-gray-600">This order is pending payment. It is not paid until an admin marks it paid, which is the testing path when Stripe is not connected.</p>
            @elseif($order->status->value === 'paid')
                <p class="mt-1 text-sm text-gray-600">This order is paid.</p>
            @elseif($order->status->value === 'failed')
                <p class="mt-1 text-sm text-gray-600">This payment did not complete. You can start a new purchase.</p>
            @endif
        </article>

        @if($order->status->value === 'pending')
            <div class="sk-card space-y-4 p-6">
                @if($order->shouldChargeWithStripe())
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
            </div>
        @endif
    </div>
</x-app-layout>
