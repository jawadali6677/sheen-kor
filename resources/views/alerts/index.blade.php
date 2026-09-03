<x-app-layout>

    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Environmental Alerts
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

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

            <div class="mb-6">
                <a href="{{ route('alerts.create') }}" class="px-5 py-2 bg-red-700 text-white rounded">
                    + Report Alert
                </a>
            </div>

            <form method="GET" action="{{ route('alerts.index') }}" class="mb-6 bg-white rounded-lg shadow p-4">
                <div class="row g-3 align-items-end">
                    <div class="col-md-8">
                        <label for="alert-search" class="form-label text-muted small mb-1">Search alerts</label>
                        <input
                            id="alert-search"
                            type="search"
                            name="q"
                            value="{{ $search }}"
                            class="form-control"
                            placeholder="Search by title, description, or place..."
                        >
                    </div>
                    <div class="col-md-4">
                        <label for="alert-status" class="form-label text-muted small mb-1">Status</label>
                        <select id="alert-status" name="status" class="form-select" onchange="this.form.submit()">
                            <option value="">All statuses</option>
                            <option value="open" @selected($status === 'open')>Open</option>
                            <option value="in_progress" @selected($status === 'in_progress')>In progress</option>
                            <option value="fixed" @selected($status === 'fixed')>Fixed</option>
                        </select>
                    </div>
                </div>
            </form>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">

                @forelse($alerts as $alert)

                    <div class="bg-white rounded-lg shadow overflow-hidden">
                        <a href="{{ route('alerts.show', $alert) }}">
                            <img
                                src="{{ asset('storage/' . $alert->featured_image) }}"
                                alt="{{ $alert->title }}"
                                class="w-full h-56 object-cover"
                            >
                        </a>

                        <div class="p-5">
                            <span class="text-xs uppercase tracking-wide
                                @if($alert->severity === 'high') text-red-600
                                @elseif($alert->severity === 'medium') text-orange-600
                                @else text-green-600
                                @endif">
                                {{ $alert->severity }} severity
                            </span>

                            <h3 class="text-xl font-semibold mt-2">
                                <a href="{{ route('alerts.show', $alert) }}" class="hover:underline">
                                    {{ $alert->title }}
                                </a>
                            </h3>

                            <p class="text-sm text-gray-500 mt-2">
                                {{ $alert->location_name }}
                                ·
                                @if($alert->user)
                                    <a href="{{ route('authors.show', $alert->user) }}" class="text-gray-700 font-medium hover:underline">
                                        {{ $alert->user->name }}
                                    </a>
                                @else
                                    Unknown User
                                @endif
                            </p>

                            <p class="text-gray-600 mt-3">
                                {{ \Illuminate\Support\Str::limit($alert->description, 120) }}
                            </p>

                            <p class="text-sm text-gray-500 mt-3 flex items-center gap-2">
                                @include('alerts.partials.status-badge', ['alert' => $alert])
                                <span>· {{ $alert->views }} views</span>
                            </p>

                            @if($alert->isInProgress() && $alert->actionUser)
                                <p class="text-sm text-blue-700 mt-2">
                                    Being handled by {{ $alert->actionUser->name }}
                                </p>
                            @elseif($alert->isFixed() && $alert->actionUser)
                                <p class="text-sm text-green-700 mt-2">
                                    Fixed by {{ $alert->actionUser->name }}
                                </p>
                            @endif

                            @include('posts.partials.engagement-bar', [
                                'model' => $alert,
                                'liked' => (bool) $alert->liked_by_user,
                                'likesCount' => $alert->likes_count,
                                'commentsCount' => $alert->comments_count,
                            ])

                            <div class="mt-4">
                                <a href="{{ route('alerts.show', $alert) }}" class="text-blue-600 mr-4">
                                    View Alert
                                </a>

                                @if(auth()->id() === $alert->user_id)
                                    <a href="{{ route('alerts.edit', $alert) }}" class="text-green-600 mr-4">Edit</a>

                                    <form
                                        action="{{ route('alerts.destroy', $alert) }}"
                                        method="POST"
                                        class="inline"
                                        onsubmit="return confirm('Delete this alert?')"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600">Delete</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>

                @empty
                    <div class="col-span-full text-center py-12">
                        <p class="text-gray-500 text-lg">
                            No alerts yet. Report pollution, dumping, or other environmental harm.
                        </p>
                    </div>
                @endforelse

            </div>

            <div class="mt-8">
                {{ $alerts->links() }}
            </div>

        </div>
    </div>

    @include('posts.partials.engagement-assets')

</x-app-layout>
