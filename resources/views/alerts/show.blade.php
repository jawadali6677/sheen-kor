<x-app-layout>
    <div class="mx-auto max-w-4xl space-y-6">
        <x-flash />

        @if($alert->hasMedia())
            <div class="overflow-hidden rounded-3xl">
                <x-media-carousel :slides="$alert->mediaSlides()" />
            </div>
        @endif

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
        </div>

        <article class="sk-card p-6 md:p-8">
            <h2 class="text-lg font-semibold text-forest-900">Description</h2>
            <div class="prose mt-3 max-w-none text-gray-700">{!! nl2br(e($alert->description)) !!}</div>
        </article>

        <section class="sk-card overflow-hidden">
            <div class="p-6">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Location</p>
                <p class="mt-1 text-lg font-semibold text-forest-900">{{ $alert->location_name ?: 'Location not specified' }}</p>
                @if($alert->latitude && $alert->longitude)
                    <p class="mt-1 text-sm text-gray-500">
                        Coordinates: {{ number_format((float) $alert->latitude, 5) }}, {{ number_format((float) $alert->longitude, 5) }}
                    </p>
                @endif
            </div>
            @if($alert->latitude && $alert->longitude)
                @include('partials.location-map', [
                    'mapId' => 'alert-report-map',
                    'readonly' => true,
                    'lat' => $alert->latitude,
                    'lng' => $alert->longitude,
                    'name' => '',
                    'mapClass' => 'location-map-prominent',
                    'wrapperClass' => 'mb-0',
                ])
            @endif
        </section>

        <article class="sk-card p-6 md:p-8">
            <div class="space-y-3 rounded-2xl border p-4
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
                        <form
                            action="{{ route('alerts.take-action', $alert) }}"
                            method="POST"
                            data-confirm="Take this alert?"
                            data-confirm-message="Others will not be able to take it while you work on it."
                            data-confirm-action="Take action"
                            data-confirm-variant="primary"
                        >
                            @csrf
                            <button type="submit" class="btn-primary">Take action</button>
                        </form>
                    @else
                        <a href="{{ route('login') }}" class="btn-primary">Log in to take action</a>
                    @endauth
                @elseif($alert->isInProgress())
                    <p>In progress @if($alert->actionUser) by <strong>{{ $alert->actionUser->name }}</strong> @endif</p>
                    @if($alert->canBeFixedBy(auth()->id()))
                        <form
                            action="{{ route('alerts.mark-fixed', $alert) }}"
                            method="POST"
                            enctype="multipart/form-data"
                            class="space-y-4"
                            data-confirm="Mark this alert as fixed?"
                            data-confirm-message="This will mark the report as resolved."
                            data-confirm-action="Mark as Fixed"
                            data-confirm-variant="primary"
                        >
                            @csrf
                            <x-media-uploader
                                label="Add evidence photos/videos"
                                hint="Optional photos or short clips of the cleaned place. Photos 5 MB each, videos 20 MB, up to 3 videos."
                                featured-name=""
                                images-name="fix_images"
                                videos-name="fix_videos"
                                :map-first-image-to-featured="false"
                                :max-images="10"
                            />
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
                            <button type="submit" class="btn-accent text-forest-900">Mark as Fixed</button>
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
                        <div class="mt-4 overflow-hidden rounded-2xl">
                            <x-media-carousel :slides="$alert->fixImages->map(fn ($media) => [
                                'src' => asset('storage/'.$media->image),
                                'type' => $media->isVideo() ? 'video' : 'image',
                                'alt' => 'After the fix',
                            ])->all()" />
                        </div>
                    @endif
                @endif
            </div>

            <div class="mt-8 flex flex-wrap gap-3">
                @can('update', $alert)
                    <a href="{{ route('alerts.edit', $alert) }}" class="btn-primary">Edit Alert</a>
                @endcan
                @can('delete', $alert)
                    <form
                        action="{{ route('alerts.destroy', $alert) }}"
                        method="POST"
                        data-confirm="Delete this alert?"
                        data-confirm-message="This action cannot be undone."
                        data-confirm-action="Delete"
                    >
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
