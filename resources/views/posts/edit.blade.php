@php
    $simpleEditor = $post->usesGeneratedTitle() && ! request()->boolean('advanced');
    $simpleErrors = $simpleEditor
        ? collect($errors->all())->map(fn (string $message): string => friendly_post_message($message))->values()->all()
        : [];
@endphp

<x-app-layout>

    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Edit post
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="{{ $simpleEditor ? 'mx-auto max-w-xl' : 'max-w-4xl mx-auto sm:px-6 lg:px-8' }}">

            @unless($simpleEditor)
                @if ($errors->any())
                    <div class="mb-6 p-4 bg-red-100 text-red-700 rounded">
                        <ul class="list-disc list-inside">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if(session('error'))
                    <div class="mb-6 p-4 bg-red-100 text-red-700 rounded">
                        {{ session('error') }}
                    </div>
                @endif
            @endunless

            @if($simpleEditor)
                <div
                    x-data="quickPostComposer({
                        embedded: true,
                        text: @js((string) old('content', $post->content)),
                        open: true,
                        errors: @js($simpleErrors),
                    })"
                >
                    @include('posts.partials.quick-post-fields', [
                        'post' => $post,
                        'categories' => $categories,
                        'embedded' => true,
                        'formClass' => 'sk-card p-4 sm:p-6',
                    ])
                </div>
            @else
            <div class="bg-white shadow-sm sm:rounded-lg p-6">

                <form
                    action="{{ route('posts.update', $post) }}"
                    method="POST"
                    enctype="multipart/form-data"
                    class="relative"
                    x-data="postComposer()"
                    @submit="onSubmit($event)"
                >

                    @csrf
                    @method('PUT')


                    <div class="mb-6">
                        <label for="title" class="block font-medium text-sm text-gray-700">Title</label>
                        <input
                            type="text"
                            name="title"
                            id="title"
                            value="{{ old('title', $post->usesGeneratedTitle() ? '' : $post->title) }}"
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
                            placeholder="Add a title (optional)"
                        >
                    </div>

                    <div class="mb-6">
                        <label for="category_id" class="block font-medium text-sm text-gray-700">Category</label>
                        <select name="category_id" id="category_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                            <option value="">Select Category</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" @selected(old('category_id', $post->category_id) == $category->id)>
                                    {{ $category->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-6">
                        <label for="excerpt" class="block font-medium text-sm text-gray-700">Short Description</label>
                        <textarea name="excerpt" id="excerpt" rows="3" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">{{ old('excerpt', $post->excerpt) }}</textarea>
                    </div>

                    <div class="mb-6">
                        <label for="content" class="block font-medium text-sm text-gray-700">Post</label>
                        <textarea name="content" id="content" rows="12" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">{{ old('content', $post->content) }}</textarea>
                    </div>

                    @if($post->hasMedia())
                        <div class="mb-6">
                            <p class="mb-3 text-sm font-medium text-gray-700">Current media</p>
                            <div class="flex flex-wrap gap-3">
                                @if($post->featured_image)
                                    <img
                                        src="{{ asset('storage/' . $post->featured_image) }}"
                                        alt="{{ $post->title }}"
                                        class="h-32 w-40 rounded-xl object-cover js-lightbox"
                                    >
                                @endif
                                @foreach($post->images as $image)
                                    <div class="h-32 w-40 overflow-hidden rounded-xl">
                                        <x-media-item :media="$image" alt="Post media" class="h-32 w-40 object-cover" />
                                    </div>
                                @endforeach
                            </div>
                            <p class="mt-2 text-sm text-gray-500">Existing media stays unless you add a new cover photo. New files are added to the gallery.</p>
                        </div>
                    @endif

                    <x-media-uploader
                        label="Add photos & videos"
                        hint="New photos and videos are added to this post. The first new photo replaces the cover."
                    />

                    <div class="flex items-center gap-4">
                        <button type="submit" class="btn-primary" x-bind:disabled="submitting">Save</button>
                        <a href="{{ $post->usesGeneratedTitle() ? route('posts.edit', $post) : route('posts.index') }}" class="btn-secondary">Cancel</a>
                    </div>

                    <div
                        x-cloak
                        x-show="submitting"
                        class="absolute inset-0 z-10 flex flex-col items-center justify-center gap-2 rounded-xl bg-white/90 px-6 text-center"
                        role="status"
                        aria-live="polite"
                        aria-busy="true"
                    >
                        <p class="font-semibold text-forest-900">Checking your post…</p>
                        <p class="text-sm text-gray-500">Verifying content...</p>
                    </div>

                </form>

            </div>
            @endif

        </div>
    </div>

</x-app-layout>
