<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-6">
        <x-flash />

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]">
            <img src="{{ asset('storage/'.$alert->featured_image) }}" alt="{{ $alert->title }}" class="max-h-[36rem] w-full rounded-3xl object-cover js-lightbox">

            <div class="sk-card space-y-4 p-6">
                <div class="flex flex-wrap gap-2">
                    <x-severity-badge :severity="$alert->severity" />
                    <x-status-badge :alert="$alert" />
                </div>
                <h1 class="text-3xl font-bold text-forest-900">{{ $alert->title }}</h1>
                <p class="text-sm text-gray-500">
                    Reported by
                    @if($alert->user)
                        <a href="{{ route('users.show', $alert->user) }}" class="font-semibold text-forest-800 hover:underline">{{ $alert->user->name }}</a>
                    @else
                        Unknown User
                    @endif
                    · {{ $alert->created_at?->format('M d, Y') }}
                </p>
                <x-location-card
                    :name="$alert->location_name"
                    :lat="$alert->latitude"
                    :lng="$alert->longitude"
                    map-id="alert-report-map"
                />
            </div>
        </div>

        <article class="sk-card p-6 md:p-8">
            <h2 class="text-lg font-semibold text-forest-900">Description</h2>
            <div class="prose mt-3 max-w-none text-gray-700">{!! nl2br(e($alert->description)) !!}</div>

            <div class="mt-8 space-y-3 rounded-2xl border p-4
                @if($alert->isFixed()) border-forest-100 bg-forest-50
                @elseif($alert->isInProgress()) border-sky-100 bg-sky-50
                @else border-amber-100 bg-amber-50
                @endif">
                <h3 class="font-semibold text-forest-900">Status history</h3>
                <ol class="space-y-2 text-sm text-gray-700">
                    <li>Reported {{ $alert->created_at?->format('M d, Y') }}</li>
                    @if($alert->action_taken_at)
                        <li>Taken on {{ $alert->action_taken_at->format('M d, Y') }} @if($alert->actionUser) by {{ $alert->actionUser->name }} @endif</li>
                    @endif
                    @if($alert->fixed_at)
                        <li>Resolved {{ $alert->fixed_at->format('M d, Y') }}</li>
                    @endif
                </ol>

                @if($alert->isOpen())
                    <p class="text-gray-800">This alert is open. Someone can take action to clean or fix it.</p>
                    @auth
                        <form action="{{ route('alerts.take-action', $alert) }}" method="POST">
                            @csrf
                            <button type="submit" class="btn-primary" onclick="return confirm('Take this alert? Others will not be able to take it while you work on it.')">Take action</button>
                        </form>
                    @else
                        <a href="{{ route('login') }}" class="btn-primary">Log in to take action</a>
                    @endauth
                @elseif($alert->isInProgress())
                    <p>In progress @if($alert->actionUser) by <strong>{{ $alert->actionUser->name }}</strong> @endif</p>
                    @if($alert->canBeFixedBy(auth()->id()))
                        <form action="{{ route('alerts.mark-fixed', $alert) }}" method="POST" enctype="multipart/form-data" class="space-y-4">
                            @csrf
                            <div>
                                <label for="fix_images" class="block text-sm font-medium text-gray-700">After photos (optional)</label>
                                <input type="file" name="fix_images[]" id="fix_images" multiple accept="image/jpeg,image/png,image/webp" class="sk-input">
                            </div>
                            @include('partials.short-video-input', [
                                'field' => 'fix_videos',
                                'inputId' => 'fix_videos',
                                'label' => 'After videos (optional)',
                                'hint' => 'Short clips of the cleaned place. Up to 3 videos, 20 MB each.',
                            ])
                            @include('partials.location-map', [
                                'mapId' => 'alert-fix-map',
                                'latName' => 'fixed_latitude',
                                'lngName' => 'fixed_longitude',
                                'nameField' => 'fixed_location_name',
                                'nameLabel' => 'Location after the fix (optional)',
                                'requiredName' => false,
                                'lat' => old('fixed_latitude'),
                                'lng' => old('fixed_longitude'),
                                'name' => old('fixed_location_name'),
                            ])
                            <button type="submit" class="btn-accent text-forest-900" onclick="return confirm('Mark this alert as Fixed?')">Mark as Fixed</button>
                        </form>
                    @else
                        <p class="text-sm text-gray-600">This alert is already being handled.</p>
                    @endif
                @else
                    <p class="font-medium text-forest-800">Resolved @if($alert->actionUser) by {{ $alert->actionUser->name }} @endif</p>
                    @if($alert->fixed_latitude && $alert->fixed_longitude)
                        <x-location-card :name="$alert->fixed_location_name" :lat="$alert->fixed_latitude" :lng="$alert->fixed_longitude" map-id="alert-fixed-map" />
                    @endif
                    @if($alert->fixImages->count())
                        <div class="grid grid-cols-2 gap-3 md:grid-cols-3">
                            @foreach($alert->fixImages as $image)
                                <x-media-item :media="$image" alt="After the fix" class="h-40 w-full rounded-xl object-cover" />
                            @endforeach
                        </div>
                    @endif
                @endif
            </div>

            @if($alert->reportImages->count())
                <div class="mt-10 grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3">
                    @foreach($alert->reportImages as $image)
                        <x-media-item :media="$image" :alt="$image->caption ?? $alert->title" class="h-64 w-full rounded-xl object-cover" />
                    @endforeach
                </div>
            @endif

            <div class="mt-8 flex flex-wrap gap-3">
                @can('update', $alert)
                    <a href="{{ route('alerts.edit', $alert) }}" class="btn-primary">Edit Alert</a>
                @endcan
                @can('delete', $alert)
                    <form action="{{ route('alerts.destroy', $alert) }}" method="POST" onsubmit="return confirm('Delete this alert?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn-secondary text-red-700">Delete Alert</button>
                    </form>
                @endcan
                <a href="{{ route('alerts.index') }}" class="btn-secondary">Back to Alerts</a>
            </div>
        </article>

        <div class="sk-card p-5">
            @include('posts.partials.engagement-bar', [
                'model' => $alert,
                'liked' => $likedByUser,
                'likesCount' => $likesCount,
                'commentsCount' => $commentsCount,
            ])
        </div>
    </div>

    @include('posts.partials.engagement-assets')
</x-app-layout>
