<x-app-layout>
    <div class="mx-auto max-w-5xl space-y-6">
        <x-flash />

        <article class="sk-card overflow-hidden">
            @if($post->featured_image)
                <img src="{{ asset('storage/'.$post->featured_image) }}" alt="{{ $post->title }}" class="max-h-[32rem] w-full object-cover js-lightbox">
            @endif
            <div class="p-6 md:p-10">
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
                <div class="prose mt-8 max-w-none text-gray-700">{!! nl2br(e($post->content)) !!}</div>

                @if($post->images->count())
                    <div class="mt-10 grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3">
                        @foreach($post->images as $image)
                            <x-media-item :media="$image" :alt="$image->caption ?? $post->title" class="h-64 w-full rounded-xl object-cover" />
                        @endforeach
                    </div>
                @endif

                <div class="mt-10 flex flex-wrap gap-3 border-t border-gray-100 pt-6">
                    @can('update', $post)
                        <a href="{{ route('posts.edit', $post) }}" class="btn-primary">Edit Story</a>
                    @endcan
                    @can('delete', $post)
                        <form action="{{ route('posts.destroy', $post) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this story?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-secondary text-red-700">Delete Story</button>
                        </form>
                    @endcan
                    <a href="{{ route('posts.index') }}" class="btn-secondary">Back to Stories</a>
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
