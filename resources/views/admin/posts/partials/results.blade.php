<div
    data-admin-posts-meta
    data-status="{{ $status }}"
    data-search="{{ $search }}"
    data-counts='@json($counts)'
></div>

<div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
    <div class="hidden overflow-x-auto md:block">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-left">
                <tr>
                    <th class="px-4 py-3">ID</th>
                    <th class="px-4 py-3">Author</th>
                    <th class="px-4 py-3">Story</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Created</th>
                    <th class="px-4 py-3">Published</th>
                    <th class="px-4 py-3">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($posts as $post)
                    <tr class="border-t">
                        <td class="px-4 py-3 text-gray-500">{{ $post->id }}</td>
                        <td class="px-4 py-3">{{ $post->user?->name ?? 'Unknown' }}</td>
                        <td class="px-4 py-3">
                            <a href="{{ route('admin.posts.show', $post) }}" class="font-medium text-forest-800 hover:underline">{{ $post->title }}</a>
                            <p class="mt-1 text-gray-500">{{ \Illuminate\Support\Str::limit(strip_tags((string) ($post->excerpt ?: $post->content)), 90) }}</p>
                        </td>
                        <td class="px-4 py-3 capitalize">{{ str_replace('_', ' ', $post->status) }}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-gray-500">{{ $post->created_at?->format('M d, Y') }}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-gray-500">{{ $post->published_at?->format('M d, Y') ?? '—' }}</td>
                        <td class="px-4 py-3">
                            @include('admin.posts.partials.row-actions', ['post' => $post])
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-10 text-center text-gray-500">No stories match this filter.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="space-y-3 p-4 md:hidden">
        @forelse($posts as $post)
            <div class="rounded-lg border border-gray-100 p-4">
                <p class="text-xs text-gray-500">#{{ $post->id }} · {{ $post->user?->name ?? 'Unknown' }}</p>
                <p class="mt-1 font-medium text-forest-900">{{ $post->title }}</p>
                <p class="mt-1 text-sm text-gray-500">{{ \Illuminate\Support\Str::limit(strip_tags((string) ($post->excerpt ?: $post->content)), 90) }}</p>
                <p class="mt-2 text-xs capitalize text-gray-600">{{ str_replace('_', ' ', $post->status) }} · {{ $post->created_at?->format('M d, Y') }}</p>
                <div class="mt-3">
                    @include('admin.posts.partials.row-actions', ['post' => $post])
                </div>
            </div>
        @empty
            <p class="py-8 text-center text-gray-500">No stories match this filter.</p>
        @endforelse
    </div>
</div>

<div class="mt-6" data-admin-posts-pagination>
    {{ $posts->links() }}
</div>
