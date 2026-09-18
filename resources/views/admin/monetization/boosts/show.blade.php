<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Post boost</h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-3xl space-y-6 sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="rounded bg-green-100 p-4 text-green-700">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="rounded bg-red-100 p-4 text-red-700">{{ session('error') }}</div>
            @endif

            <p><a href="{{ route('admin.monetization.boosts.index') }}" class="text-sm text-blue-700">Back to boosts</a></p>

            <section class="rounded-lg bg-white p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-gray-800">{{ $boost->user?->name }}</h3>
                <p class="text-sm text-gray-600">{{ $boost->user?->email }}</p>
                <p class="mt-2 text-sm text-gray-600">Post: {{ $boost->post?->title ?? 'Deleted post' }} (ID {{ $boost->post_id }})</p>
                <p class="mt-2 text-sm text-gray-600">Status: {{ $boost->displayStatus()->label() }} · {{ $boost->source->label() }}</p>
                <p class="mt-1 text-sm text-gray-600">{{ $boost->package_name }} · {{ $boost->price }} {{ $boost->currency }} · {{ $boost->duration_days }} days</p>
                @if($boost->starts_at)
                    <p class="mt-1 text-sm text-gray-600">{{ $boost->starts_at->toDayDateTimeString() }} – {{ $boost->ends_at?->toDayDateTimeString() }}</p>
                @endif
                @if($boost->notes)
                    <p class="mt-3 text-sm text-gray-700">Notes: {{ $boost->notes }}</p>
                @endif
            </section>

            @if($boost->status->value === 'pending')
                <section class="flex flex-wrap gap-3 rounded-lg bg-white p-6 shadow-sm">
                    <form method="POST" action="{{ route('admin.monetization.boosts.activate', $boost) }}">
                        @csrf
                        <button type="submit" class="rounded bg-gray-800 px-4 py-2 text-sm text-white">Activate for testing</button>
                    </form>
                    <form method="POST" action="{{ route('admin.monetization.boosts.cancel', $boost) }}">
                        @csrf
                        <button type="submit" class="rounded bg-red-700 px-4 py-2 text-sm text-white">Cancel</button>
                    </form>
                </section>
            @elseif($boost->status->value === 'active' && $boost->isCurrentlyActive())
                <section class="rounded-lg bg-white p-6 shadow-sm">
                    <form method="POST" action="{{ route('admin.monetization.boosts.cancel', $boost) }}">
                        @csrf
                        <button type="submit" class="rounded bg-red-700 px-4 py-2 text-sm text-white">Cancel boost</button>
                    </form>
                </section>
            @endif
        </div>
    </div>
</x-app-layout>
