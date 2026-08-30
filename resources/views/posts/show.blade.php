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
                            href="#"
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
                            <strong class="text-gray-700">
                                {{ $post->user?->name ?? 'Unknown User' }}
                            </strong>
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

        </div>

    </div>

</x-app-layout>