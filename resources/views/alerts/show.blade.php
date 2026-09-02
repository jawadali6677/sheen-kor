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
                    class="w-full max-h-[550px] object-cover"
                >

                <div class="p-6 md:p-10">
                    <div class="flex flex-wrap gap-2 text-sm">
                        <span class="px-2 py-1 rounded
                            @if($alert->severity === 'high') bg-red-100 text-red-700
                            @elseif($alert->severity === 'medium') bg-orange-100 text-orange-700
                            @else bg-green-100 text-green-700
                            @endif">
                            {{ ucfirst($alert->severity) }} severity
                        </span>
                        <span class="px-2 py-1 rounded bg-gray-100 text-gray-700">
                            {{ ucfirst($alert->status) }}
                        </span>
                    </div>

                    <h1 class="text-3xl md:text-4xl font-bold mt-3">{{ $alert->title }}</h1>

                    <div class="mt-4 text-sm text-gray-500">
                        {{ $alert->location_name }}
                        · Reported by
                        @if($alert->user)
                            <a href="{{ route('authors.show', $alert->user) }}" class="text-gray-700 font-semibold hover:underline">
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
                        <p class="mt-2 text-sm text-gray-500">
                            Coordinates: {{ $alert->latitude }}, {{ $alert->longitude }}
                        </p>
                    @endif

                    <div class="mt-8 prose max-w-none">
                        {!! nl2br(e($alert->description)) !!}
                    </div>

                    @if($alert->images->count())
                        <div class="mt-10">
                            <h2 class="text-2xl font-semibold mb-5">More photos</h2>
                            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-5">
                                @foreach($alert->images as $image)
                                    <img
                                        src="{{ asset('storage/' . $image->image) }}"
                                        alt="{{ $image->caption ?? $alert->title }}"
                                        class="w-full h-64 object-cover rounded-lg"
                                    >
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="border-t mt-10 pt-6 flex flex-wrap items-center gap-4">
                        @if(auth()->id() === $alert->user_id)
                            <a href="{{ route('alerts.edit', $alert) }}" class="px-4 py-2 bg-green-600 text-white rounded">
                                Edit Alert
                            </a>
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
                        @endif

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
