<x-app-layout>

    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Create Story
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto">
            <x-flash />
            <div class="sk-card p-6 md:p-8">

                <form
                    action="{{ route('posts.store') }}"
                    method="POST"
                    enctype="multipart/form-data"
                >

                    @csrf

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
                            value="{{ old('title') }}"
                            class="sk-input"
                            placeholder="Enter your story title"
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
                            class="sk-input"
                            required
                        >
                            <option value="">
                                Select Category
                            </option>

                            @foreach($categories as $category)

                                <option
                                    value="{{ $category->id }}"
                                    @selected(old('category_id') == $category->id)
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
                            class="sk-input"
                            placeholder="Write a short description..."
                        >{{ old('excerpt') }}</textarea>
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
                            class="sk-input"
                            placeholder="Write your story..."
                            required
                        >{{ old('content') }}</textarea>
                    </div>


                    {{-- Featured Image --}}
                    <div class="mb-6">
                        <label
                            for="featured_image"
                            class="block font-medium text-sm text-gray-700"
                        >
                            Featured Image
                        </label>

                        <input
                            type="file"
                            name="featured_image"
                            id="featured_image"
                            accept="image/jpeg,image/png,image/webp"
                            class="mt-1 block w-full"
                        >

                        <p class="text-sm text-gray-500 mt-1">
                            Maximum size: 5 MB. The photo appears below so you can check it before you submit. Click it to open it larger.
                        </p>
                    </div>


                    {{-- Additional Images --}}
                    <div class="mb-6">
                        <label
                            for="images"
                            class="block font-medium text-sm text-gray-700"
                        >
                            Additional Images
                        </label>

                        <input
                            type="file"
                            name="images[]"
                            id="images"
                            multiple
                            accept="image/jpeg,image/png,image/webp"
                            class="mt-1 block w-full"
                        >

                        <p class="text-sm text-gray-500 mt-1">
                            You can upload up to 10 additional images.
                            Maximum 5 MB each.
                        </p>
                    </div>

                    @include('partials.short-video-input')


                    {{-- Buttons --}}
                    <div class="flex items-center gap-4">

                        <button type="submit" class="btn-primary">
                            Submit Story
                        </button>

                        <a href="{{ route('posts.index') }}" class="btn-secondary">
                            Cancel
                        </a>

                    </div>

                </form>

            </div>

        </div>
    </div>

</x-app-layout>