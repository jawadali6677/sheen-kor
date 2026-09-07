@props(['post'])

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
                @if($post->category)
                    <a href="{{ route('categories.show', $post->category) }}" class="hover:underline">{{ $post->category->name }}</a>
                    ·
                @endif
                {{ ($post->published_at ?? $post->created_at)?->diffForHumans() }}
            </p>
        </div>
    </div>

    <a href="{{ route('posts.show', $post) }}" class="block bg-gray-900">
        @if($post->featured_image)
            <img src="{{ asset('storage/'.$post->featured_image) }}" alt="{{ $post->title }}" class="max-h-[28rem] w-full object-cover" loading="lazy">
        @else
            <div class="flex aspect-[4/3] items-center justify-center bg-forest-800 px-6 text-center text-lg font-semibold text-white">{{ $post->title }}</div>
        @endif
    </a>

    <div class="space-y-2 px-4 py-3">
        @include('posts.partials.engagement-bar', [
            'post' => $post,
            'liked' => (bool) $post->liked_by_user,
            'likesCount' => $post->likes_count,
            'commentsCount' => $post->comments_count,
            'compact' => true,
        ])
        <h3 class="text-base font-semibold text-forest-900">
            <a href="{{ route('posts.show', $post) }}" class="hover:underline">{{ $post->title }}</a>
        </h3>
        @if($post->excerpt)
            <p class="text-sm text-gray-600">{{ \Illuminate\Support\Str::limit($post->excerpt, 140) }}</p>
        @endif
        @can('update', $post)
            <div class="flex gap-3 pt-1 text-sm">
                <a href="{{ route('posts.edit', $post) }}" class="text-gray-500 hover:text-forest-800">Edit</a>
                @can('delete', $post)
                    <form action="{{ route('posts.destroy', $post) }}" method="POST" onsubmit="return confirm('Delete this story?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-red-600">Delete</button>
                    </form>
                @endcan
            </div>
        @endcan
    </div>
</article>
