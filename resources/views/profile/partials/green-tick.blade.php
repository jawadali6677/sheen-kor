<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">Green Tick</h2>
        <p class="mt-1 text-sm text-gray-600">
            Request verification. Confirming creates a pending order. An admin marks it paid for testing, then reviews it if review is required.
        </p>
    </header>

    @if(session('success'))
        <div class="mt-4 rounded bg-green-100 p-3 text-sm text-green-700">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mt-4 rounded bg-red-100 p-3 text-sm text-red-700">{{ session('error') }}</div>
    @endif

    @if($greenTickVerification)
        <div class="mt-4 rounded-lg border border-gray-200 p-4 text-sm">
            <p class="font-medium text-gray-900">Status: {{ $greenTickVerification->displayStatus()->label() }}</p>
            <p class="mt-1 text-gray-600">{{ $greenTickVerification->package_name }} · {{ $greenTickVerification->price }} {{ $greenTickVerification->currency }} · {{ $greenTickVerification->duration_days }} days</p>
            @if($greenTickVerification->isCurrentlyActive())
                <p class="mt-1 text-gray-600">Active until {{ $greenTickVerification->ends_at?->toFormattedDateString() }}.</p>
            @endif
            @if(in_array($greenTickVerification->status->value, ['pending_payment', 'pending_review'], true))
                <form method="POST" action="{{ route('green-tick.destroy', $greenTickVerification) }}" class="mt-3">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="text-sm text-red-600">Cancel request</button>
                </form>
            @endif
        </div>
    @endif

    <div class="mt-4 text-sm text-gray-700">
        <p class="font-medium">Eligibility</p>
        <ul class="mt-2 list-disc space-y-1 ps-5">
            <li>{{ number_format($greenTickEligibility['followers']) }} / {{ number_format($greenTickEligibility['min_followers']) }} followers</li>
            <li>{{ number_format($greenTickEligibility['published_posts']) }} / {{ number_format($greenTickEligibility['min_published_posts']) }} published posts</li>
            <li>{{ number_format($greenTickEligibility['qualified_views']) }} / {{ number_format($greenTickEligibility['min_qualified_views']) }} qualified views in 30 days</li>
        </ul>
        @if(! $greenTickEligibility['eligible'])
            <p class="mt-2 text-gray-500">Qualified 30-day views are not tracked yet, so that requirement is 0 until view tracking is added. An admin can lower the views threshold for testing.</p>
        @endif
    </div>

    @if($greenTickEligibility['eligible'] && ! $user->hasOpenGreenTickRequest())
        @if($greenTickPackages->isEmpty())
            <p class="mt-4 text-sm text-gray-500">No Green Tick packages are enabled.</p>
        @else
            <form method="POST" action="{{ route('green-tick.store') }}" class="mt-6 space-y-4">
                @csrf
                <div>
                    <x-input-label for="package_id" value="Package" />
                    <select id="package_id" name="package_id" class="mt-1 block w-full rounded-md border-gray-300" required>
                        @foreach($greenTickPackages as $package)
                            <option value="{{ $package->id }}">{{ $package->name }} · {{ $package->price }} {{ $package->currency }} · {{ $package->duration_days }} days</option>
                        @endforeach
                    </select>
                    <x-input-error class="mt-2" :messages="$errors->get('package_id')" />
                </div>
                <button type="submit" class="rounded bg-gray-800 px-4 py-2 text-sm text-white">Request Green Tick</button>
            </form>
        @endif
    @elseif($greenTickEligibility['eligible'] && $user->hasOpenGreenTickRequest())
        <p class="mt-4 text-sm text-gray-500">You already have a pending or active Green Tick.</p>
    @else
        <p class="mt-4 text-sm text-gray-500">You are not eligible to request a Green Tick yet.</p>
    @endif
</section>
