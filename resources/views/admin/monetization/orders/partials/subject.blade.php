@if($order->packageType()?->value === 'listing_promotion')
    @if($listing = $order->listing ?? $order->listingPromotion?->listing)
        @can('market.moderate')
            <a href="{{ route('admin.market.show', $listing) }}" class="text-blue-700 hover:underline">{{ $listing->title }}</a>
        @else
            <p>{{ $listing->title }}</p>
        @endcan
    @else
        <p class="text-gray-500">Deleted listing</p>
    @endif
@elseif($order->packageType()?->value === 'post_boost')
    @if($post = $order->post ?? $order->boost?->post)
        @can('posts.moderate')
            <a href="{{ route('admin.posts.show', $post) }}" class="text-blue-700 hover:underline">{{ $post->title }}</a>
        @else
            <p>{{ $post->title }}</p>
        @endcan
    @else
        <p class="text-gray-500">Deleted post</p>
    @endif
@elseif($order->packageType()?->value === 'green_tick')
    @if($order->user)
        <a href="{{ route('users.show', $order->user) }}" class="text-blue-700 hover:underline">{{ $order->user->name }}</a>
    @else
        <p class="text-gray-500">Deleted member</p>
    @endif
@else
    <p>{{ $order->purchasedItemName() }}</p>
@endif
