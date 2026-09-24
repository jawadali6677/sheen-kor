<x-app-layout>
    <div class="mx-auto max-w-3xl space-y-6">
        <x-flash />

        <article class="sk-card p-6">
            <p class="text-sm text-gray-500">Promote listing</p>
            <h1 class="mt-1 text-2xl font-bold text-forest-900">{{ $listing->title }}</h1>
            @if($openPromotion?->isCurrentlyActive())
                <p class="mt-2 text-sm text-gray-600">This listing is already promoted.</p>
            @elseif($openPromotion?->status->value === 'pending' && $openPromotion->order?->shouldChargeWithStripe())
                <p class="mt-2 text-sm text-gray-600">Finish payment to start this promotion. This promotion is reserved until you pay with Stripe. Pending payment is not paid, and the listing is not featured yet.</p>
            @elseif($openPromotion)
                <p class="mt-2 text-sm text-gray-600">Finish payment to start this promotion. This promotion is reserved and is not paid. An admin can mark the order paid for testing until Stripe Checkout is connected. The listing is not featured yet.</p>
            @elseif(stripe_checkout_is_configured())
                <p class="mt-2 text-sm text-gray-600">Choose a package. Confirming reserves a pending promotion and opens Stripe Checkout. The listing is not featured until Stripe confirms payment.</p>
            @else
                <p class="mt-2 text-sm text-gray-600">Choose how long to promote this listing, then continue to payment. The promotion starts after payment succeeds.</p>
            @endif
        </article>

        @if($openPromotion)
            <section class="sk-card p-6 text-sm">
                <p class="font-medium text-gray-900">Status: {{ $openPromotion->ownerStatusHeadline() }}</p>
                <p class="mt-1 text-gray-600">{{ $openPromotion->package_name }} · {{ $openPromotion->placement->label() }} · {{ $openPromotion->price }} {{ $openPromotion->currency }} · {{ $openPromotion->duration_days }} days</p>
                @if($openPromotion->isCurrentlyActive() && $openPromotion->activeUntilPhrase())
                    <p class="mt-1 font-medium text-amber-800">{{ $openPromotion->activeUntilPhrase() }}.</p>
                @endif
                @if($openPromotion->ownerStatusExplanation())
                    <p class="mt-2 text-gray-600">{{ $openPromotion->ownerStatusExplanation() }}</p>
                @endif
                @if($openPromotion->status->value === 'pending')
                    <div class="mt-4 flex flex-wrap items-center gap-3">
                        @if($openPromotion->order?->shouldChargeWithStripe())
                            <form method="POST" action="{{ route('orders.pay', $openPromotion->order) }}">
                                @csrf
                                <button type="submit" class="btn-primary">Continue payment</button>
                            </form>
                        @endif
                        @if($openPromotion->order)
                            <a href="{{ route('orders.show', $openPromotion->order) }}" class="btn-primary">Continue to payment</a>
                            <a href="{{ route('orders.show', $openPromotion->order) }}" class="btn-secondary">View order</a>
                        @endif
                        <form method="POST" action="{{ route('market.promote.destroy', $openPromotion) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-sm text-red-600">Cancel pending promotion</button>
                        </form>
                    </div>
                @endif
            </section>
        @elseif($packages->isEmpty())
            <p class="sk-card p-6 text-sm text-gray-600">No listing promotion packages are enabled.</p>
        @else
            <form method="POST" action="{{ route('market.promote.store', $listing) }}" class="sk-card space-y-4 p-6">
                @csrf
                @foreach($packages as $package)
                    <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-gray-200 p-4">
                        <input type="radio" name="package_id" value="{{ $package->id }}" class="mt-1" @checked($loop->first) required>
                        <span>
                            <span class="block font-medium text-gray-900">{{ $package->name }}</span>
                            <span class="mt-1 block text-sm text-gray-600">{{ $package->price }} {{ $package->currency }} · {{ $package->duration_days }} {{ $package->duration_days === 1 ? 'day' : 'days' }}</span>
                        </span>
                    </label>
                @endforeach
                <div class="flex flex-wrap gap-3">
                    @if(stripe_checkout_is_configured())
                        <button type="submit" class="btn-primary">Confirm pending promotion</button>
                    @else
                        <button type="submit" class="btn-primary">Continue to payment</button>
                    @endif
                    <a href="{{ route('market.show', $listing) }}" class="btn-secondary">Cancel</a>
                </div>
            </form>
        @endif
    </div>
</x-app-layout>
