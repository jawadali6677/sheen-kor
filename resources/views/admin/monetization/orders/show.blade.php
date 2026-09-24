<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Order #{{ $order->id }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-3xl space-y-6 sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="rounded bg-green-100 p-4 text-green-700">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="rounded bg-red-100 p-4 text-red-700">{{ session('error') }}</div>
            @endif

            <p><a href="{{ route('admin.monetization.orders.index') }}" class="text-sm text-blue-700">Back to orders</a></p>

            <section class="rounded-lg bg-white p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-gray-800">{{ $order->user?->name }}</h3>
                <p class="text-sm text-gray-600">{{ $order->user?->email }}</p>
                <p class="mt-2 text-sm text-gray-600">Status: {{ $order->status->label() }}</p>
                <p class="mt-1 text-sm text-gray-600">{{ $order->snapshot['name'] ?? $order->package?->name }} · {{ $order->amount }} {{ $order->currency }}</p>
                @if($order->boost)
                    <p class="mt-1 text-sm text-gray-600">Post: {{ $order->boost->post?->title ?? 'Deleted post' }}</p>
                @endif
                @if($order->listingPromotion)
                    <p class="mt-1 text-sm text-gray-600">Listing: {{ $order->listingPromotion->listing?->title ?? 'Deleted listing' }}</p>
                @endif
                @if($order->verification)
                    <p class="mt-1 text-sm text-gray-600">Green Tick: {{ $order->verification->displayStatus()->label() }}</p>
                @endif
                @foreach($order->payments as $payment)
                    <p class="mt-1 text-sm text-gray-600">
                        Payment {{ $payment->provider->label() }} · {{ $payment->status->label() }}
                        @if($payment->provider === \App\Enums\PaymentProvider::Stripe && filled($payment->provider_reference))
                            · Stripe session {{ $payment->provider_reference }}
                        @endif
                    </p>
                @endforeach
            </section>

            @if($order->status->value === 'pending')
                <section class="flex flex-wrap gap-3 rounded-lg bg-white p-6 shadow-sm">
                    <form method="POST" action="{{ route('admin.monetization.orders.mark-paid', $order) }}">
                        @csrf
                        <button type="submit" class="rounded bg-gray-800 px-4 py-2 text-sm text-white">Mark paid</button>
                    </form>
                    <form method="POST" action="{{ route('admin.monetization.orders.cancel', $order) }}">
                        @csrf
                        <button type="submit" class="rounded bg-red-700 px-4 py-2 text-sm text-white">Cancel</button>
                    </form>
                </section>
            @endif
        </div>
    </div>
</x-app-layout>
