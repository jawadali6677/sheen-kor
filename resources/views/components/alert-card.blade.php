@props(['alert'])

<article class="sk-card">
    <div class="flex items-center gap-3 px-4 py-3">
        @if($alert->user)
            <a href="{{ route('users.show', $alert->user) }}">
                <x-user-avatar :user="$alert->user" size="sm" />
            </a>
        @endif
        <div class="min-w-0 flex-1">
            @if($alert->user)
                <a href="{{ route('users.show', $alert->user) }}" class="font-semibold text-forest-900 hover:underline">{{ $alert->user->name }}</a>
            @else
                <span class="font-semibold text-forest-900">Unknown User</span>
            @endif
            <p class="text-xs text-gray-500">{{ $alert->location_name }} · {{ $alert->created_at?->diffForHumans() }}</p>
        </div>
        <x-status-badge :alert="$alert" />
    </div>

    <a href="{{ route('alerts.show', $alert) }}" class="block bg-gray-900">
        <img src="{{ asset('storage/'.$alert->featured_image) }}" alt="{{ $alert->title }}" class="max-h-[28rem] w-full object-cover" loading="lazy">
    </a>

    <div class="space-y-2 px-4 py-3">
        @include('posts.partials.engagement-bar', [
            'model' => $alert,
            'liked' => (bool) $alert->liked_by_user,
            'likesCount' => $alert->likes_count,
            'commentsCount' => $alert->comments_count,
            'compact' => true,
        ])
        <div class="flex flex-wrap items-center gap-2">
            <x-severity-badge :severity="$alert->severity" />
        </div>
        <h3 class="text-base font-semibold text-forest-900">
            <a href="{{ route('alerts.show', $alert) }}" class="hover:underline">{{ $alert->title }}</a>
        </h3>
        <p class="text-sm text-gray-600">{{ \Illuminate\Support\Str::limit($alert->description, 140) }}</p>
    </div>
</article>
