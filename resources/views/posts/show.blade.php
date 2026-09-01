<x-app-layout>

    <x-slot name="header">

        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $post->title }}
        </h2>

    </x-slot>


    <div class="py-8">

        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">

            {{-- Success --}}
            @if(session('success'))

                <div class="mb-6 p-4 bg-green-100 text-green-700 rounded">
                    {{ session('success') }}
                </div>

            @endif


            {{-- Error --}}
            @if(session('error'))

                <div class="mb-6 p-4 bg-red-100 text-red-700 rounded">
                    {{ session('error') }}
                </div>

            @endif


            <article class="bg-white shadow-sm rounded-lg overflow-hidden">


                {{-- Featured Image --}}
                @if($post->featured_image)

                    <div>

                        <img
                            src="{{ asset('storage/' . $post->featured_image) }}"
                            alt="{{ $post->title }}"
                            class="w-full max-h-[550px] object-cover"
                        >

                    </div>

                @endif


                <div class="p-6 md:p-10">


                    {{-- Category --}}
                    @if($post->category)

                        <a
                            href="{{ route('categories.show', $post->category) }}"
                            class="text-sm text-blue-600"
                        >
                            {{ $post->category->name }}
                        </a>

                    @endif


                    {{-- Title --}}
                    <h1 class="text-3xl md:text-4xl font-bold mt-3">
                        {{ $post->title }}
                    </h1>


                    {{-- Author Information --}}
                    <div class="flex items-center mt-4 text-sm text-gray-500">

                        <span>
                            By
                            @if($post->user)
                                <a href="{{ route('authors.show', $post->user) }}" class="text-gray-700 font-semibold hover:underline">
                                    {{ $post->user->name }}
                                </a>
                            @else
                                <strong class="text-gray-700">Unknown User</strong>
                            @endif
                        </span>

                        @if($post->published_at)

                            <span class="mx-2">
                                ·
                            </span>

                            <span>
                                {{ $post->published_at->format('M d, Y') }}
                            </span>

                        @endif

                        <span class="mx-2">
                            ·
                        </span>

                        <span>
                            {{ $post->views }} views
                        </span>

                        <span class="mx-2">
                            ·
                        </span>

                        <span>
                            {{ $likesCount }} likes
                        </span>

                        <span class="mx-2">
                            ·
                        </span>

                        <span>
                            {{ $commentsCount }} comments
                        </span>

                    </div>


                    {{-- Excerpt --}}
                    @if($post->excerpt)

                        <div class="mt-6 text-lg text-gray-600">
                            {{ $post->excerpt }}
                        </div>

                    @endif


                    {{-- Article Content --}}
                    <div class="mt-8 prose max-w-none">

                        {!! nl2br(e($post->content)) !!}

                    </div>


                    {{-- Additional Images --}}
                    @if($post->images->count())

                        <div class="mt-10">

                            <h2 class="text-2xl font-semibold mb-5">
                                Photos
                            </h2>


                            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-5">

                                @foreach($post->images as $image)

                                    <div>

                                        <img
                                            src="{{ asset('storage/' . $image->image) }}"
                                            alt="{{ $image->caption ?? $post->title }}"
                                            class="w-full h-64 object-cover rounded-lg"
                                        >


                                        @if($image->caption)

                                            <p class="text-sm text-gray-500 mt-2">
                                                {{ $image->caption }}
                                            </p>

                                        @endif

                                    </div>

                                @endforeach

                            </div>

                        </div>

                    @endif


                    {{-- Actions --}}
                    <div class="border-t mt-10 pt-6 flex items-center gap-4">


                        {{-- Owner Only --}}
                        @auth

                            @if(auth()->id() === $post->user_id)

                                <a
                                    href="{{ route('posts.edit', $post) }}"
                                    class="px-4 py-2 bg-green-600 text-white rounded"
                                >
                                    Edit Story
                                </a>


                                <form
                                    action="{{ route('posts.destroy', $post) }}"
                                    method="POST"
                                    onsubmit="return confirm('Are you sure you want to delete this story?')"
                                >

                                    @csrf
                                    @method('DELETE')

                                    <button
                                        type="submit"
                                        class="px-4 py-2 bg-red-600 text-white rounded"
                                    >
                                        Delete Story
                                    </button>

                                </form>

                            @endif

                        @endauth


                        <a
                            href="{{ route('posts.index') }}"
                            class="px-4 py-2 bg-gray-200 text-gray-700 rounded"
                        >
                            Back to Stories
                        </a>

                    </div>

                </div>

            </article>

            @if($post->status === 'published')
                <section
                    class="mt-4"
                    id="post-engagement"
                    data-post-id="{{ $post->id }}"
                    data-like-url="{{ route('posts.likes.store', $post) }}"
                    data-unlike-url="{{ route('posts.likes.destroy', $post) }}"
                    data-comment-url="{{ route('posts.comments.store', $post) }}"
                    data-comment-update-template="{{ url('comments/__ID__') }}"
                    data-liked="{{ $likedByUser ? '1' : '0' }}"
                    data-auth-name="{{ auth()->user()->name }}"
                >
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <div
                                id="engagement-alert"
                                class="alert d-none"
                                role="alert"
                            ></div>

                            <div class="d-flex flex-wrap align-items-center gap-3 mb-4">
                                <button
                                    type="button"
                                    id="like-button"
                                    class="btn {{ $likedByUser ? 'btn-danger' : 'btn-outline-danger' }}"
                                >
                                    <span id="like-icon">{{ $likedByUser ? '♥' : '♡' }}</span>
                                    <span id="like-label">{{ $likedByUser ? 'Liked' : 'Like' }}</span>
                                </button>

                                <span class="badge text-bg-secondary fs-6">
                                    <span id="likes-count">{{ $likesCount }}</span> likes
                                </span>

                                <span class="badge text-bg-primary fs-6">
                                    <span id="comments-count">{{ $commentsCount }}</span> comments
                                </span>
                            </div>

                            <h3 class="h5 mb-3">Comments</h3>

                            <form id="comment-form" class="mb-4">
                                <label for="comment-content" class="form-label">Add a comment</label>
                                <textarea
                                    id="comment-content"
                                    class="form-control"
                                    rows="3"
                                    maxlength="2000"
                                    placeholder="Share your thoughts (at least 3 characters)..."
                                    required
                                ></textarea>
                                <div class="invalid-feedback d-block" id="comment-error"></div>
                                <button type="submit" class="btn btn-primary mt-2" id="comment-submit">
                                    Post comment
                                </button>
                            </form>

                            <div id="comments-empty" class="{{ $comments->count() ? 'd-none' : '' }} text-muted">
                                No comments yet. Be the first to share your thoughts.
                            </div>

                            <div id="comments-list">
                                @foreach($comments as $comment)
                                    @include('posts.partials.comment', ['comment' => $comment, 'post' => $post])
                                @endforeach
                            </div>
                        </div>
                    </div>
                </section>
            @endif

        </div>

    </div>

    @push('styles')
        <link
            href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
            rel="stylesheet"
        >
        <style>
            #post-engagement .comment-content {
                white-space: pre-wrap;
                word-break: break-word;
            }
        </style>
    @endpush

    @push('scripts')
        <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
        <script src="{{ asset('js/post-engagement.js') }}"></script>
    @endpush

</x-app-layout>