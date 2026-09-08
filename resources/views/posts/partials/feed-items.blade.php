@forelse($posts as $index => $post)
    <x-post-card :post="$post" />
    @if((($posts->firstItem() ?? 1) + $index) % 5 === 0)
        <x-in-feed-ad :ad="demo_ads()[($index) % count(demo_ads())]" />
    @endif
@empty
    @unless(request()->boolean('partial') || request()->hasHeader('X-Infinite-Scroll'))
        <x-empty-state title="No posts yet" :action-label="auth()->check() ? 'Create a post' : 'Join Sheen Kor'" :action-url="auth()->check() ? route('posts.create') : route('register')">
            Be the first to share a photo, story, or idea with the community.
        </x-empty-state>
    @endunless
@endforelse
<div data-infinite-next="{{ $posts->hasMorePages() ? $posts->nextPageUrl() : '' }}" hidden></div>
