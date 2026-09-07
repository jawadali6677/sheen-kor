<x-app-layout>

    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $alert->title }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">

            @if(session('success'))
                <div class="mb-6 p-4 bg-green-100 text-green-700 rounded">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="mb-6 p-4 bg-red-100 text-red-700 rounded">
                    {{ session('error') }}
                </div>
            @endif

            <article class="bg-white shadow-sm rounded-lg overflow-hidden">
                <img
                    src="{{ asset('storage/' . $alert->featured_image) }}"
                    alt="{{ $alert->title }}"
                    class="w-full max-h-[550px] object-cover js-lightbox"
                >

                <div class="p-6 md:p-10">
                    <div class="flex flex-wrap items-center gap-2 text-sm">
                        <span class="px-2 py-1 rounded
                            @if($alert->severity === 'high') bg-red-100 text-red-700
                            @elseif($alert->severity === 'medium') bg-orange-100 text-orange-700
                            @else bg-green-100 text-green-700
                            @endif">
                            {{ ucfirst($alert->severity) }} severity
                        </span>
                        @include('alerts.partials.status-badge', ['alert' => $alert])
                    </div>

                    <h1 class="text-3xl md:text-4xl font-bold mt-3">{{ $alert->title }}</h1>

                    <div class="mt-4 text-sm text-gray-500">
                        Reported by
                        @if($alert->user)
                            <a href="{{ route('users.show', $alert->user) }}" class="text-gray-700 font-semibold hover:underline">
                                {{ $alert->user->name }}
                            </a>
                        @else
                            Unknown User
                        @endif
                        · {{ $alert->created_at?->format('M d, Y') }}
                        · {{ $likesCount }} likes
                        · {{ $commentsCount }} comments
                        · {{ $alert->views }} views
                    </div>

                    @if($alert->latitude && $alert->longitude)
                        @include('partials.location-map', [
                            'mapId' => 'alert-report-map',
                            'readonly' => true,
                            'lat' => $alert->latitude,
                            'lng' => $alert->longitude,
                            'name' => $alert->location_name,
                        ])
                    @else
                        <p class="mt-2 text-sm text-gray-500">{{ $alert->location_name }}</p>
                    @endif

                    <div class="mt-8 prose max-w-none">
                        {!! nl2br(e($alert->description)) !!}
                    </div>

                    <div class="mt-8 p-4 rounded-lg border
                        @if($alert->isFixed()) border-green-200 bg-green-50
                        @elseif($alert->isInProgress()) border-blue-200 bg-blue-50
                        @else border-amber-200 bg-amber-50
                        @endif">
                        @if($alert->isOpen())
                            <p class="text-gray-800 font-medium">This alert is open. Someone can take action to clean or fix it.</p>
                            <form action="{{ route('alerts.take-action', $alert) }}" method="POST" class="mt-3">
                                @csrf
                                <button
                                    type="submit"
                                    class="px-4 py-2 bg-blue-700 text-white rounded"
                                    onclick="return confirm('Take this alert? Others will not be able to take it while you work on it.')"
                                >
                                    Take action
                                </button>
                            </form>
                        @elseif($alert->isInProgress())
                            <p class="text-gray-800">
                                In progress
                                @if($alert->actionUser)
                                    by <strong>{{ $alert->actionUser->name }}</strong>
                                @endif
                                @if($alert->action_taken_at)
                                    since {{ $alert->action_taken_at->format('M d, Y') }}
                                @endif
                            </p>
                            @if($alert->canBeFixedBy(auth()->id()))
                                <form
                                    action="{{ route('alerts.mark-fixed', $alert) }}"
                                    method="POST"
                                    enctype="multipart/form-data"
                                    class="mt-4 space-y-4"
                                >
                                    @csrf

                                    <div>
                                        <label for="fix_images" class="block font-medium text-sm text-gray-700">
                                            After photos (optional)
                                        </label>
                                        <input
                                            type="file"
                                            name="fix_images[]"
                                            id="fix_images"
                                            multiple
                                            accept="image/jpeg,image/png,image/webp"
                                            class="mt-1 block w-full"
                                        >
                                        <p class="text-sm text-gray-500 mt-1">Show the cleaned place. Up to 10 images, 5 MB each. Preview them below, then click to open larger.</p>
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

                                    <button
                                        type="submit"
                                        class="px-4 py-2 bg-green-700 text-white rounded"
                                        onclick="return confirm('Mark this alert as Fixed?')"
                                    >
                                        Mark as Fixed
                                    </button>
                                </form>
                            @else
                                <p class="text-sm text-gray-600 mt-2">
                                    This alert is already being handled. Others cannot take it until it is finished.
                                </p>
                            @endif
                        @else
                            <p class="text-green-800 font-medium flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-6 h-6">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
                                </svg>
                                Fixed
                                @if($alert->actionUser)
                                    by {{ $alert->actionUser->name }}
                                @endif
                                @if($alert->fixed_at)
                                    on {{ $alert->fixed_at->format('M d, Y') }}
                                @endif
                            </p>
                            <p class="text-sm text-gray-600 mt-2">
                                This alert is closed. Nobody else can take action on it.
                            </p>

                            @if($alert->fixed_location_name || ($alert->fixed_latitude && $alert->fixed_longitude))
                                @if($alert->fixed_latitude && $alert->fixed_longitude)
                                    @include('partials.location-map', [
                                        'mapId' => 'alert-fixed-map',
                                        'readonly' => true,
                                        'lat' => $alert->fixed_latitude,
                                        'lng' => $alert->fixed_longitude,
                                        'name' => $alert->fixed_location_name,
                                    ])
                                @else
                                    <p class="text-sm text-gray-700 mt-3">Fixed at {{ $alert->fixed_location_name }}</p>
                                @endif
                            @endif

                            @if($alert->fixImages->count())
                                <div class="mt-4">
                                    <p class="font-medium text-gray-800 mb-3">After photos and videos</p>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                                        @foreach($alert->fixImages as $image)
                                            <x-media-item :media="$image" alt="After the fix" class="w-full h-40 object-cover rounded-lg" />
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        @endif
                    </div>

                    @if($alert->reportImages->count())
                        <div class="mt-10">
                            <h2 class="text-2xl font-semibold mb-5">More photos and videos</h2>
                            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-5">
                                @foreach($alert->reportImages as $image)
                                    <x-media-item :media="$image" :alt="$image->caption ?? $alert->title" class="w-full h-64 object-cover rounded-lg" />
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="border-t mt-10 pt-6 flex flex-wrap items-center gap-4">
                        @canany(['update', 'delete'], $alert)
                            @can('update', $alert)
                            <a href="{{ route('alerts.edit', $alert) }}" class="px-4 py-2 bg-green-600 text-white rounded">
                                Edit Alert
                            </a>
                            @endcan
                            @can('delete', $alert)
                            <form
                                action="{{ route('alerts.destroy', $alert) }}"
                                method="POST"
                                onsubmit="return confirm('Delete this alert?')"
                            >
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded">
                                    Delete Alert
                                </button>
                            </form>
                            @endcan
                        @endcanany

                        <a href="{{ route('alerts.index') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded">
                            Back to Alerts
                        </a>
                    </div>
                </div>
            </article>

            <div class="bg-white shadow-sm rounded-lg p-4 mt-4">
                @include('posts.partials.engagement-bar', [
                    'model' => $alert,
                    'liked' => $likedByUser,
                    'likesCount' => $likesCount,
                    'commentsCount' => $commentsCount,
                ])
            </div>

        </div>
    </div>

    @include('posts.partials.engagement-assets')

</x-app-layout>
