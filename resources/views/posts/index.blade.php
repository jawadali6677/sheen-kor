<x-app-layout>

    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            @if($category)
                {{ $category->name }}
            @elseif($author)
                Stories by {{ $author->name }}
            @else
                Sheen Kor Stories
            @endif
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

            @auth
            <div class="mb-6">
                <a
                    href="{{ route('posts.create') }}"
                    class="px-5 py-2 bg-gray-800 text-white rounded">
                    + Create Story
                </a>
            </div>
            @endauth

            <form
                method="GET"
                action="{{ $author ? route('authors.show', $author) : route('posts.index') }}"
                class="mb-6 bg-white rounded-lg shadow p-4"
            >
                <div class="row g-3 align-items-end">
                    <div class="col-md-8">
                        <label for="story-search" class="form-label text-muted small mb-1">Search stories</label>
                        <input
                            id="story-search"
                            type="search"
                            name="q"
                            value="{{ $search }}"
                            class="form-control"
                            placeholder="Search by title or content..."
                        >
                    </div>

                    @if(! $author)
                        <div class="col-md-4">
                            <label for="category-filter" class="form-label text-muted small mb-1">Category</label>
                            <select
                                id="category-filter"
                                name="category"
                                class="form-select"
                                onchange="this.form.submit()"
                            >
                                <option value="">All categories</option>
                                @foreach($categories as $feedCategory)
                                    <option
                                        value="{{ $feedCategory->slug }}"
                                        @selected($category?->slug === $feedCategory->slug)
                                    >
                                        {{ $feedCategory->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                </div>
            </form>

            <div class="social-feed">

                @forelse($posts as $post)

                    @php
                        $authorName = $post->user?->name ?? 'Unknown User';
                        $initial = mb_strtoupper(mb_substr($authorName, 0, 1));
                    @endphp

                    <article class="feed-card">
                        <div class="feed-header">
                            <div class="feed-avatar">{{ $initial }}</div>
                            <div class="min-w-0">
                                @if($post->user)
                                    <a href="{{ route('authors.show', $post->user) }}" class="font-semibold text-gray-900">
                                        {{ $authorName }}
                                    </a>
                                @else
                                    <span class="font-semibold text-gray-900">{{ $authorName }}</span>
                                @endif
                                <div class="feed-meta">
                                    @if($post->category)
                                        <a href="{{ route('categories.show', $post->category) }}">{{ $post->category->name }}</a>
                                        ·
                                    @endif
                                    @if($post->published_at)
                                        {{ $post->published_at->format('M d') }}
                                    @else
                                        {{ $post->created_at?->format('M d') }}
                                    @endif
                                    · {{ $post->views }} views
                                </div>
                            </div>
                        </div>

                        <a href="{{ route('posts.show', $post) }}" class="feed-image-wrap">
                            @if($post->featured_image)
                                <img src="{{ asset('storage/' . $post->featured_image) }}" alt="{{ $post->title }}">
                            @else
                                <div class="feed-placeholder">{{ $post->title }}</div>
                            @endif
                        </a>

                        <div class="feed-body">
                            @include('posts.partials.engagement-bar', [
                                'post' => $post,
                                'liked' => (bool) $post->liked_by_user,
                                'likesCount' => $post->likes_count,
                                'commentsCount' => $post->comments_count,
                                'compact' => true,
                            ])

                            <h3 class="feed-title">
                                <a href="{{ route('posts.show', $post) }}">{{ $post->title }}</a>
                            </h3>

                            @if($post->excerpt)
                                <p class="text-gray-600 text-sm">
                                    {{ \Illuminate\Support\Str::limit($post->excerpt, 140) }}
                                </p>
                            @endif

                            @can('update', $post)
                                <div class="mt-2 text-sm">
                                    <a href="{{ route('posts.edit', $post) }}" class="text-gray-500 mr-3">Edit</a>
                                    @can('delete', $post)
                                    <form action="{{ route('posts.destroy', $post) }}" method="POST" class="inline" onsubmit="return confirm('Delete this story?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600">Delete</button>
                                    </form>
                                    @endcan
                                </div>
                            @endcan
                        </div>
                    </article>

                @empty
                    <p class="text-center text-gray-500 py-12">No stories available yet.</p>
                @endforelse

            </div>


            <div class="mt-8">
                {{ $posts->links() }}
            </div>

        </div>

    </div>

    @include('posts.partials.engagement-assets')

</x-app-layout>
