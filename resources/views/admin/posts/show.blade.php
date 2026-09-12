<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ $post->title }}</h2>
            <a href="{{ route('admin.posts.index', ['status' => $post->status === 'published' || $post->status === 'rejected' ? $post->status : 'pending']) }}" class="text-sm text-forest-800 hover:underline">Back to stories</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="mb-6 rounded bg-green-100 p-4 text-green-700">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="mb-6 rounded bg-red-100 p-4 text-red-700">{{ session('error') }}</div>
            @endif

            <article class="bg-white p-6 shadow-sm sm:rounded-lg">
                <p class="text-sm text-gray-500">
                    #{{ $post->id }}
                    · {{ $post->user?->name ?? 'Unknown' }}
                    · <span class="capitalize">{{ str_replace('_', ' ', $post->status) }}</span>
                    · Created {{ $post->created_at?->format('M d, Y') }}
                    @if($post->published_at)
                        · Published {{ $post->published_at->format('M d, Y') }}
                    @endif
                </p>

                @if($post->excerpt)
                    <p class="mt-4 text-gray-700">{{ $post->excerpt }}</p>
                @endif

                <div class="mt-4 whitespace-pre-line text-gray-800">{{ $post->content }}</div>
            </article>

            <div class="mt-6 flex flex-wrap gap-2">
                @if($post->status !== 'published')
                    <form method="POST" action="{{ route('admin.posts.publish', $post) }}">
                        @csrf
                        <button type="submit" class="rounded-md bg-forest-800 px-4 py-2 text-sm text-white">Publish</button>
                    </form>
                @endif
                @if($post->status !== 'pending')
                    <form method="POST" action="{{ route('admin.posts.pending', $post) }}">
                        @csrf
                        <button type="submit" class="rounded-md bg-amber-600 px-4 py-2 text-sm text-white">Set pending</button>
                    </form>
                @endif
                @if($post->status !== 'rejected')
                    <form method="POST" action="{{ route('admin.posts.reject', $post) }}">
                        @csrf
                        <button type="submit" class="rounded-md bg-gray-800 px-4 py-2 text-sm text-white">Reject</button>
                    </form>
                @endif
                <form method="POST" action="{{ route('admin.posts.destroy', $post) }}" data-confirm="Delete this story?" data-confirm-message="This action cannot be undone." data-confirm-action="Delete">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="rounded-md bg-red-600 px-4 py-2 text-sm text-white">Delete</button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
