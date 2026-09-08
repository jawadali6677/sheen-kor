@props(['post'])

@php
    $slides = $post->mediaSlides();
    $hasMedia = $post->hasMedia();
@endphp

<article class="sk-card">
    <div class="flex items-center gap-3 px-4 py-3">
        @if($post->user)
            <a href="{{ route('users.show', $post->user) }}">
                <x-user-avatar :user="$post->user" size="sm" />
            </a>
        @endif
        <div class="min-w-0">
            @if($post->user)
                <a href="{{ route('users.show', $post->user) }}" class="font-semibold text-forest-900 hover:underline">{{ $post->user->name }}</a>
            @else
                <span class="font-semibold text-forest-900">Unknown User</span>
            @endif
            <p class="text-xs text-gray-500">
                {{ ($post->published_at ?? $post->created_at)?->diffForHumans() }}
                @if($post->category)
                    ·
                    <a href="{{ route('categories.show', $post->category) }}" class="hover:underline">{{ $post->category->name }}</a>
                @endif
            </p>
        </div>
    </div>

    @if($hasMedia)
        <x-media-carousel :slides="$slides" :href="route('posts.show', $post)" />
    @endif

    <div class="space-y-2 px-4 py-3">
        <h3 class="text-base font-semibold text-forest-900 {{ $hasMedia ? '' : 'text-xl' }}">
            <a href="{{ route('posts.show', $post) }}" class="hover:underline">{{ $post->title }}</a>
        </h3>
        @if($post->excerpt)
            <p class="text-sm leading-6 text-gray-600">{{ \Illuminate\Support\Str::limit($post->excerpt, $hasMedia ? 140 : 220) }}</p>
        @elseif(! $hasMedia)
            <p class="text-sm leading-6 text-gray-600">{{ \Illuminate\Support\Str::limit(strip_tags($post->content), 220) }}</p>
        @endif
        <a href="{{ route('posts.show', $post) }}" class="inline-flex text-sm font-semibold text-forest-800 hover:underline">Read more →</a>
        @include('posts.partials.engagement-bar', [
            'post' => $post,
            'liked' => (bool) $post->liked_by_user,
            'likesCount' => $post->likes_count,
            'commentsCount' => $post->comments_count,
            'compact' => true,
        ])
        @can('update', $post)
            <div class="flex gap-3 pt-1 text-sm">
                <a href="{{ route('posts.edit', $post) }}" class="text-gray-500 hover:text-forest-800">Edit</a>
                @can('delete', $post)
                    <form
                        action="{{ route('posts.destroy', $post) }}"
                        method="POST"
                        data-confirm="Delete this post?"
                        data-confirm-message="This action cannot be undone."
                        data-confirm-action="Delete"
                    >
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-red-600">Delete</button>
                    </form>
                @endcan
            </div>
        @endcan
    </div>
</article>
