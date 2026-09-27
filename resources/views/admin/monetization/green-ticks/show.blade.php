<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Green Tick request</h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-3xl space-y-6 sm:px-6 lg:px-8">
            <p><a href="{{ route('admin.monetization.green-ticks.index') }}" class="text-sm text-blue-700">Back to requests</a></p>

            <section class="rounded-lg bg-white p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-gray-800">{{ $verification->user?->name }}</h3>
                <p class="text-sm text-gray-600">{{ $verification->user?->email }} · {{ $verification->user?->username ? '@'.$verification->user->username : '' }}</p>
                <p class="mt-2 text-sm text-gray-600">Status: {{ $verification->displayStatus()->label() }} · {{ $verification->source->label() }}</p>
                <p class="mt-1 text-sm text-gray-600">{{ $verification->package_name }} · {{ $verification->price }} {{ $verification->currency }} · {{ $verification->duration_days }} days</p>
                @if($verification->starts_at)
                    <p class="mt-1 text-sm text-gray-600">{{ $verification->starts_at->toDayDateTimeString() }} – {{ $verification->ends_at?->toDayDateTimeString() }}</p>
                @endif
                @if($verification->review_notes)
                    <p class="mt-3 text-sm text-gray-700">Notes: {{ $verification->review_notes }}</p>
                @endif

                @if($eligibility)
                    <div class="mt-4 text-sm text-gray-700">
                        <p class="font-medium">Eligibility at review</p>
                        <ul class="mt-2 list-disc ps-5">
                            <li>{{ number_format($eligibility['followers']) }} / {{ number_format($eligibility['min_followers']) }} followers</li>
                            <li>{{ number_format($eligibility['published_posts']) }} / {{ number_format($eligibility['min_published_posts']) }} published posts</li>
                            <li>{{ number_format($eligibility['qualified_views']) }} / {{ number_format($eligibility['min_qualified_views']) }} qualified views (30 days)</li>
                        </ul>
                    </div>
                @endif
            </section>

            @if($verification->status->value === 'pending_review')
                <section class="rounded-lg bg-white p-6 shadow-sm">
                    <form method="POST" action="{{ route('admin.monetization.green-ticks.approve', $verification) }}" class="space-y-4">
                        @csrf
                        <div>
                            <label for="review_notes" class="block text-sm font-medium text-gray-700">Notes (optional)</label>
                            <textarea id="review_notes" name="review_notes" rows="3" class="mt-1 block w-full rounded-md border-gray-300 text-sm"></textarea>
                        </div>
                        <button type="submit" class="rounded bg-gray-800 px-4 py-2 text-sm text-white">Approve</button>
                    </form>
                    <form method="POST" action="{{ route('admin.monetization.green-ticks.reject', $verification) }}" class="mt-4 space-y-4">
                        @csrf
                        <div>
                            <label for="reject_notes" class="block text-sm font-medium text-gray-700">Rejection notes (optional)</label>
                            <textarea id="reject_notes" name="review_notes" rows="3" class="mt-1 block w-full rounded-md border-gray-300 text-sm"></textarea>
                        </div>
                        <button type="submit" class="rounded bg-red-700 px-4 py-2 text-sm text-white">Reject</button>
                    </form>
                </section>
            @endif
        </div>
    </div>
</x-app-layout>
