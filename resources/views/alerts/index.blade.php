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

            <div class="social-feed">

                @forelse($alerts as $alert)

                    @php
                        $authorName = $alert->user?->name ?? 'Unknown User';
                        $initial = mb_strtoupper(mb_substr($authorName, 0, 1));
                    @endphp

                    <article class="feed-card">
                        <div class="feed-header">
                            <div class="feed-avatar">{{ $initial }}</div>
                            <div class="min-w-0 flex-1">
                                @if($alert->user)
                                    <a href="{{ route('authors.show', $alert->user) }}" class="font-semibold text-gray-900">
                                        {{ $authorName }}
                                    </a>
                                @else
                                    <span class="font-semibold text-gray-900">{{ $authorName }}</span>
                                @endif
                                <div class="feed-meta">
                                    {{ $alert->location_name }}
                                    · {{ $alert->created_at?->format('M d') }}
                                </div>
                            </div>
                            @include('alerts.partials.status-badge', ['alert' => $alert])
                        </div>

                        <a href="{{ route('alerts.show', $alert) }}" class="feed-image-wrap">
                            <img src="{{ asset('storage/' . $alert->featured_image) }}" alt="{{ $alert->title }}">
                        </a>

                        <div class="feed-body">
                            @include('posts.partials.engagement-bar', [
                                'model' => $alert,
                                'liked' => (bool) $alert->liked_by_user,
                                'likesCount' => $alert->likes_count,
                                'commentsCount' => $alert->comments_count,
                                'compact' => true,
                            ])

                            <h3 class="feed-title">
                                <a href="{{ route('alerts.show', $alert) }}">{{ $alert->title }}</a>
                            </h3>

                            <p class="text-gray-600 text-sm">
                                {{ \Illuminate\Support\Str::limit($alert->description, 140) }}
                            </p>

                            @if($alert->isInProgress() && $alert->actionUser)
                                <p class="text-sm text-blue-700 mt-2">Being handled by {{ $alert->actionUser->name }}</p>
                            @elseif($alert->isFixed() && $alert->actionUser)
                                <p class="text-sm text-green-700 mt-2">Fixed by {{ $alert->actionUser->name }}</p>
                            @endif

                            @if(auth()->id() === $alert->user_id)
                                <div class="mt-2 text-sm">
                                    <a href="{{ route('alerts.edit', $alert) }}" class="text-gray-500 mr-3">Edit</a>
                                    <form action="{{ route('alerts.destroy', $alert) }}" method="POST" class="inline" onsubmit="return confirm('Delete this alert?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600">Delete</button>
                                    </form>
                                </div>
                            @endif
                        </div>
                    </article>

                @empty
                    <p class="text-center text-gray-500 py-12">
                        No alerts yet. Report pollution, dumping, or other environmental harm.
                    </p>
                @endforelse

            </div>

            <div class="mt-8">
                {{ $alerts->links() }}
            </div>

        </div>
    </div>

    @include('posts.partials.engagement-assets')

</x-app-layout>
