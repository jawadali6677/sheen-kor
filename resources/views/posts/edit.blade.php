<x-app-layout>

    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Edit Story
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">

            {{-- Validation Errors --}}
            @if ($errors->any())
                <div class="mb-6 p-4 bg-red-100 text-red-700 rounded">
                    <ul class="list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Error Message --}}
            @if(session('error'))
                <div class="mb-6 p-4 bg-red-100 text-red-700 rounded">
                    {{ session('error') }}
                </div>
            @endif


            <div class="bg-white shadow-sm sm:rounded-lg p-6">

                <form
                    action="{{ route('posts.update', $post) }}"
                    method="POST"
                    enctype="multipart/form-data"
                >

                    @csrf
                    @method('PUT')


                    {{-- Title --}}
                    <div class="mb-6">
                        <label
                            for="title"
                            class="block font-medium text-sm text-gray-700"
                        >
                            Title
                        </label>

                        <input
                            type="text"
                            name="title"
                            id="title"
                            value="{{ old('title', $post->title) }}"
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
                            required
                        >
                    </div>


                    {{-- Category --}}
                    <div class="mb-6">
                        <label
                            for="category_id"
                            class="block font-medium text-sm text-gray-700"
                        >
                            Category
                        </label>

                        <select
                            name="category_id"
                            id="category_id"
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
                            required
                        >
                            <option value="">
                                Select Category
                            </option>

                            @foreach($categories as $category)

                                <option
                                    value="{{ $category->id }}"
                                    @selected(
                                        old('category_id', $post->category_id)
                                        == $category->id
                                    )
                                >
                                    {{ $category->name }}
                                </option>

                            @endforeach
                        </select>
                    </div>


                    {{-- Excerpt --}}
                    <div class="mb-6">
                        <label
                            for="excerpt"
                            class="block font-medium text-sm text-gray-700"
                        >
                            Short Description
                        </label>

                        <textarea
                            name="excerpt"
                            id="excerpt"
                            rows="3"
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
                        >{{ old('excerpt', $post->excerpt) }}</textarea>
                    </div>


                    {{-- Content --}}
                    <div class="mb-6">
                        <label
                            for="content"
                            class="block font-medium text-sm text-gray-700"
                        >
                            Story / Article
                        </label>

                        <textarea
                            name="content"
                            id="content"
                            rows="12"
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
                            required
                        >{{ old('content', $post->content) }}</textarea>
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
                                        <x-media-item :media="$image" alt="Story media" class="h-32 w-40 object-cover" />
                                    </div>
                                @endforeach
                            </div>
                            <p class="mt-2 text-sm text-gray-500">Existing media stays unless you add a new cover photo. New files are added to the gallery.</p>
                        </div>
                    @endif

                    <x-media-uploader
                        label="Add photos & videos"
                        hint="New photos and videos are added to this story. The first new photo replaces the cover."
                    />


                    {{-- Buttons --}}
                    <div class="flex items-center gap-4">

                        <button
                            type="submit"
                            class="px-5 py-2 bg-gray-800 text-white rounded"
                        >
                            Update Story
                        </button>

                        <a
                            href="{{ route('posts.index') }}"
                            class="px-5 py-2 bg-gray-200 text-gray-700 rounded"
                        >
                            Cancel
                        </a>

                    </div>

                </form>

            </div>

        </div>
    </div>

</x-app-layout>