<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Listing promotion</h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-3xl space-y-6 sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="rounded bg-green-100 p-4 text-green-700">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="rounded bg-red-100 p-4 text-red-700">{{ session('error') }}</div>
            @endif

            <p><a href="{{ route('admin.monetization.promotions.index') }}" class="text-sm text-blue-700">Back to listing promotions</a></p>

            <section class="rounded-lg bg-white p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-gray-800">{{ $promotion->user?->name }}</h3>
                <p class="text-sm text-gray-600">{{ $promotion->user?->email }}</p>
                <p class="mt-2 text-sm text-gray-600">Listing: {{ $promotion->listing?->title ?? 'Deleted listing' }} (ID {{ $promotion->market_listing_id }})</p>
                <p class="mt-2 text-sm text-gray-600">Status: {{ $promotion->displayStatus()->label() }} · {{ $promotion->source->label() }}</p>
                @if($promotion->status->value === 'pending')
                    <p class="mt-1 text-sm text-gray-600">Pending payment is not paid. The listing is not promoted until Stripe confirms payment or you mark the related order paid.</p>
                @endif
                <p class="mt-1 text-sm text-gray-600">{{ $promotion->package_name }} · {{ $promotion->placement->label() }} · {{ $promotion->price }} {{ $promotion->currency }} · {{ $promotion->duration_days }} days</p>
                @if($promotion->starts_at)
                    <p class="mt-1 text-sm text-gray-600">{{ $promotion->starts_at->toDayDateTimeString() }} – {{ $promotion->ends_at?->toDayDateTimeString() }}</p>
                @endif
                @if($promotion->notes)
                    <p class="mt-3 text-sm text-gray-700">Notes: {{ $promotion->notes }}</p>
                @endif
            </section>

            @if($promotion->status->value === 'pending')
                <section class="flex flex-wrap gap-3 rounded-lg bg-white p-6 shadow-sm">
                    <form method="POST" action="{{ route('admin.monetization.promotions.activate', $promotion) }}">
                        @csrf
                        <button type="submit" class="rounded bg-gray-800 px-4 py-2 text-sm text-white">Activate for testing</button>
                    </form>
                    <form method="POST" action="{{ route('admin.monetization.promotions.cancel', $promotion) }}">
                        @csrf
                        <button type="submit" class="rounded bg-red-700 px-4 py-2 text-sm text-white">Cancel</button>
                    </form>
                </section>
            @elseif($promotion->status->value === 'active' && $promotion->isCurrentlyActive())
                <section class="rounded-lg bg-white p-6 shadow-sm">
                    <form method="POST" action="{{ route('admin.monetization.promotions.cancel', $promotion) }}">
                        @csrf
                        <button type="submit" class="rounded bg-red-700 px-4 py-2 text-sm text-white">Cancel promotion</button>
                    </form>
                </section>
            @endif
        </div>
    </div>
</x-app-layout>
