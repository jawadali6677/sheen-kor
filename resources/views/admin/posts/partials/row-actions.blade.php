<div class="flex flex-wrap gap-1">
    <a href="{{ route('admin.posts.show', $post) }}" class="rounded-md bg-white px-2 py-1 text-xs font-medium text-forest-800 ring-1 ring-gray-200">View</a>

    @if($post->status === 'pending')
        <form method="POST" action="{{ route('admin.posts.publish', $post) }}" data-admin-posts-action>
            @csrf
            <button type="submit" class="rounded-md bg-forest-800 px-2 py-1 text-xs font-medium text-white">Publish</button>
        </form>
        <form method="POST" action="{{ route('admin.posts.reject', $post) }}" data-admin-posts-action data-confirm="Reject this story?" data-confirm-message="The author will be told it did not meet community guidelines." data-confirm-action="Reject">
            @csrf
            <button type="submit" class="rounded-md bg-gray-800 px-2 py-1 text-xs font-medium text-white">Reject</button>
        </form>
    @elseif($post->status === 'published')
        <form method="POST" action="{{ route('admin.posts.pending', $post) }}" data-admin-posts-action>
            @csrf
            <button type="submit" class="rounded-md bg-amber-600 px-2 py-1 text-xs font-medium text-white">Set pending</button>
        </form>
        <form method="POST" action="{{ route('admin.posts.destroy', $post) }}" data-admin-posts-action data-confirm="Delete this story?" data-confirm-message="This action cannot be undone." data-confirm-action="Delete">
            @csrf
            @method('DELETE')
            <button type="submit" class="rounded-md bg-red-600 px-2 py-1 text-xs font-medium text-white">Delete</button>
        </form>
    @elseif($post->status === 'rejected')
        <form method="POST" action="{{ route('admin.posts.publish', $post) }}" data-admin-posts-action>
            @csrf
            <button type="submit" class="rounded-md bg-forest-800 px-2 py-1 text-xs font-medium text-white">Publish</button>
        </form>
        <form method="POST" action="{{ route('admin.posts.destroy', $post) }}" data-admin-posts-action data-confirm="Delete this story?" data-confirm-message="This action cannot be undone." data-confirm-action="Delete">
            @csrf
            @method('DELETE')
            <button type="submit" class="rounded-md bg-red-600 px-2 py-1 text-xs font-medium text-white">Delete</button>
        </form>
    @endif
</div>
