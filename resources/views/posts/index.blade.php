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

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">

                @forelse($posts as $post)

                <div class="bg-white rounded-lg shadow overflow-hidden">

                    @if($post->featured_image)

                    <a href="{{ route('posts.show', $post) }}">

                        <img
                            src="{{ asset('storage/' . $post->featured_image) }}"
                            alt="{{ $post->title }}"
                            class="w-full h-56 object-cover">

                    </a>

                    @else

                    <div class="w-full h-56 bg-gray-200 flex items-center justify-center">
                        <span class="text-gray-500">
                            No Image
                        </span>
                    </div>

                    @endif


                    <div class="p-5">

                        @if($post->category)

                        <a
                            href="{{ route('categories.show', $post->category) }}"
                            class="text-sm text-blue-600">
                            {{ $post->category->name }}
                        </a>

                        @endif


                        <h3 class="text-xl font-semibold mt-2">

                            <a
                                href="{{ route('posts.show', $post) }}"
                                class="hover:underline">
                                {{ $post->title }}
                            </a>

                        </h3>


                        <p class="text-sm text-gray-500 mt-2">

                            By
                            @if($post->user)
                                <a href="{{ route('authors.show', $post->user) }}" class="text-gray-700 font-medium hover:underline">
                                    {{ $post->user->name }}
                                </a>
                            @else
                                Unknown User
                            @endif

                            @if($post->published_at)
                            ·
                            {{ $post->published_at->format('M d, Y') }}
                            @endif

                        </p>


                        @if($post->excerpt)

                        <p class="text-gray-600 mt-3">
                            {{ \Illuminate\Support\Str::limit($post->excerpt, 120) }}
                        </p>

                        @endif


                        <p class="text-sm text-gray-500 mt-3">
                            {{ $post->views }} views
                        </p>


                        @include('posts.partials.engagement-bar', [
                            'post' => $post,
                            'liked' => (bool) $post->liked_by_user,
                            'likesCount' => $post->likes_count,
                            'commentsCount' => $post->comments_count,
                        ])


                        <div class="mt-4">

                            <a
                                href="{{ route('posts.show', $post) }}"
                                class="text-blue-600 mr-4">
                                Read More
                            </a>


                            @auth

                            @if(auth()->id() === $post->user_id)

                            <a
                                href="{{ route('posts.edit', $post) }}"
                                class="text-green-600 mr-4">
                                Edit
                            </a>


                            <form
                                action="{{ route('posts.destroy', $post) }}"
                                method="POST"
                                class="inline"
                                onsubmit="return confirm('Are you sure you want to delete this story?')">

                                @csrf
                                @method('DELETE')

                                <button
                                    type="submit"
                                    class="text-red-600">
                                    Delete
                                </button>

                            </form>

                            @endif

                            @endauth

                        </div>

                    </div>

                </div>

                @empty

                <div class="col-span-full text-center py-12">

                    <p class="text-gray-500 text-lg">
                        No stories available yet.
                    </p>

                </div>

                @endforelse

            </div>


            <div class="mt-8">
                {{ $posts->links() }}
            </div>

        </div>

    </div>

    @include('posts.partials.engagement-assets')

</x-app-layout>
