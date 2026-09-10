<x-app-layout>
    <div class="mx-auto max-w-5xl space-y-6">
        <x-flash />

        <article class="sk-card overflow-hidden">
            @if($post->hasMedia())
                <x-media-carousel :slides="$post->mediaSlides()" />
            @endif
            <div class="p-6 md:p-10 {{ $post->hasMedia() ? '' : 'md:px-16' }}">
                @if($post->category)
                    <a href="{{ route('categories.show', $post->category) }}" class="text-sm font-semibold text-forest-700 hover:underline">{{ $post->category->name }}</a>
                @endif
                <h1 class="mt-2 text-3xl font-bold text-forest-900 md:text-4xl">{{ $post->title }}</h1>
                <div class="mt-4 flex flex-wrap items-center gap-3 text-sm text-gray-500">
                    @if($post->user)
                        <a href="{{ route('users.show', $post->user) }}" class="inline-flex items-center gap-2 font-semibold text-forest-800">
                            <x-user-avatar :user="$post->user" size="sm" />
                            {{ $post->user->name }}
                        </a>
                    @endif
                    <span>{{ ($post->published_at ?? $post->created_at)?->format('M d, Y') }}</span>
                    <span>{{ $post->views }} views</span>
                </div>
                <div class="prose mt-8 max-w-none text-lg leading-8 text-gray-700">{!! nl2br(e($post->content)) !!}</div>

                <div class="mt-10 flex flex-wrap gap-3 border-t border-gray-100 pt-6">
                    @can('update', $post)
                        <a href="{{ route('posts.edit', $post) }}" class="btn-primary">Edit Story</a>
                    @endcan
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
                            <button type="submit" class="btn-secondary text-red-700">Delete Story</button>
                        </form>
                    @endcan
                    <a href="{{ route('posts.index') }}" class="btn-secondary" onclick="skBackToStories(event)">Back to Stories</a>
                </div>
            </div>
        </article>

        @if($post->status === 'published')
            <div class="sk-card p-5">
                @include('posts.partials.engagement-bar', [
                    'post' => $post,
                    'liked' => $likedByUser,
                    'likesCount' => $likesCount,
                    'commentsCount' => $commentsCount,
                ])
            </div>
        @endif
    </div>

    @include('posts.partials.engagement-assets')
</x-app-layout>
