@props(['alert'])

@php
    $slides = $alert->mediaSlides();
    $hasMedia = $alert->hasMedia();
@endphp

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

    @if($hasMedia)
        <x-media-carousel :slides="$slides" :href="route('alerts.show', $alert)" />
    @endif

    <div class="space-y-2 px-4 py-3">
        <div class="flex flex-wrap items-center gap-2">
            <x-severity-badge :severity="$alert->severity" />
        </div>
        <h3 class="text-base font-semibold text-forest-900">
            <a href="{{ route('alerts.show', $alert) }}" class="hover:underline">{{ $alert->title }}</a>
        </h3>
        <p class="text-sm text-gray-600">{{ \Illuminate\Support\Str::limit($alert->description, 140) }}</p>
        @include('posts.partials.engagement-bar', [
            'model' => $alert,
            'liked' => (bool) $alert->liked_by_user,
            'likesCount' => $alert->likes_count,
            'commentsCount' => $alert->comments_count,
            'compact' => true,
        ])
    </div>
</article>
