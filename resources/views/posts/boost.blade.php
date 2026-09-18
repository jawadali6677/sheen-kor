<x-app-layout>
    <div class="mx-auto max-w-3xl space-y-6">
        <x-flash />

        <article class="sk-card p-6">
            <p class="text-sm text-gray-500">Boost Post</p>
            <h1 class="mt-1 text-2xl font-bold text-forest-900">{{ $post->title }}</h1>
            <p class="mt-2 text-sm text-gray-600">Choose a package. Confirming creates a pending order. An admin marks it paid for testing until a payment provider is connected. The post is not boosted until then.</p>
        </article>

        @if($openBoost)
            <section class="sk-card p-6 text-sm">
                <p class="font-medium text-gray-900">Status: {{ $openBoost->displayStatus()->label() }}</p>
                <p class="mt-1 text-gray-600">{{ $openBoost->package_name }} · {{ $openBoost->price }} {{ $openBoost->currency }} · {{ $openBoost->duration_days }} days</p>
                @if($openBoost->isCurrentlyActive())
                    <p class="mt-1 text-gray-600">Active until {{ $openBoost->ends_at?->toFormattedDateString() }}.</p>
                @endif
                @if($openBoost->status->value === 'pending')
                    <form method="POST" action="{{ route('posts.boost.destroy', $openBoost) }}" class="mt-4">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-sm text-red-600">Cancel pending boost</button>
                    </form>
                @endif
            </section>
        @elseif($packages->isEmpty())
            <p class="sk-card p-6 text-sm text-gray-600">No boost packages are enabled.</p>
        @else
            <form method="POST" action="{{ route('posts.boost.store', $post) }}" class="sk-card space-y-4 p-6">
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
                    <button type="submit" class="btn-primary">Confirm pending boost</button>
                    <a href="{{ route('posts.show', $post) }}" class="btn-secondary">Cancel</a>
                </div>
            </form>
        @endif
    </div>
</x-app-layout>
