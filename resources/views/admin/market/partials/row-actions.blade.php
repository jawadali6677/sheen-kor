<div class="flex flex-wrap gap-1">
    <a href="{{ route('admin.market.show', $listing) }}" class="rounded-md bg-white px-2 py-1 text-xs font-medium text-forest-800 ring-1 ring-gray-200">View</a>

    @if($listing->status->value === 'pending')
        <form method="POST" action="{{ route('admin.market.publish', $listing) }}" data-admin-market-action>
            @csrf
            <button type="submit" class="rounded-md bg-forest-800 px-2 py-1 text-xs font-medium text-white">Publish</button>
        </form>
        <form method="POST" action="{{ route('admin.market.reject', $listing) }}" data-admin-market-action data-confirm="Reject this listing?" data-confirm-message="The owner will be told it did not meet community guidelines." data-confirm-action="Reject">
            @csrf
            <button type="submit" class="rounded-md bg-gray-800 px-2 py-1 text-xs font-medium text-white">Reject</button>
        </form>
    @elseif($listing->status->value === 'published')
        <form method="POST" action="{{ route('admin.market.pending', $listing) }}" data-admin-market-action>
            @csrf
            <button type="submit" class="rounded-md bg-amber-600 px-2 py-1 text-xs font-medium text-white">Set pending</button>
        </form>
        <form method="POST" action="{{ route('admin.market.destroy', $listing) }}" data-admin-market-action data-confirm="Delete this listing?" data-confirm-message="This action cannot be undone." data-confirm-action="Delete">
            @csrf
            @method('DELETE')
            <button type="submit" class="rounded-md bg-red-600 px-2 py-1 text-xs font-medium text-white">Delete</button>
        </form>
    @else
        <form method="POST" action="{{ route('admin.market.publish', $listing) }}" data-admin-market-action>
            @csrf
            <button type="submit" class="rounded-md bg-forest-800 px-2 py-1 text-xs font-medium text-white">Publish</button>
        </form>
        <form method="POST" action="{{ route('admin.market.destroy', $listing) }}" data-admin-market-action data-confirm="Delete this listing?" data-confirm-message="This action cannot be undone." data-confirm-action="Delete">
            @csrf
            @method('DELETE')
            <button type="submit" class="rounded-md bg-red-600 px-2 py-1 text-xs font-medium text-white">Delete</button>
        </form>
    @endif
</div>
