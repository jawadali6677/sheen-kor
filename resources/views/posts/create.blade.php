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
                    class="relative"
                    x-data="postComposer()"
                    @submit="onSubmit($event)"
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


                    <x-media-uploader
                        label="Add photos & videos"
                        hint="The first photo becomes the cover. You can add more photos (5 MB each) and up to 3 short videos (20 MB)."
                    />


                    {{-- Buttons --}}
                    <div class="flex items-center gap-4">

                        <button type="submit" class="btn-primary" x-bind:disabled="submitting">
                            Submit Story
                        </button>

                        <a href="{{ route('posts.index') }}" class="btn-secondary">
                            Cancel
                        </a>

                    </div>

                    <div
                        x-cloak
                        x-show="submitting"
                        class="absolute inset-0 z-10 flex flex-col items-center justify-center gap-2 rounded-xl bg-white/90 px-6 text-center"
                        role="status"
                        aria-live="polite"
                        aria-busy="true"
                    >
                        <p class="font-semibold text-forest-900">Checking your post...</p>
                        <p class="text-sm text-gray-500">Verifying content...</p>
                    </div>

                </form>

            </div>

        </div>
    </div>

</x-app-layout>