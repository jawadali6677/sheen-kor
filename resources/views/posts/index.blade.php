<x-app-layout>
    <div class="mx-auto max-w-xl space-y-4">
        <x-flash />

        <section class="sk-card p-5">
            <h1 class="text-lg font-semibold text-forest-900">What's happening around you?</h1>
            <p class="mt-1 text-sm text-gray-500">Share a story or report something that needs attention.</p>
            @auth
                <div class="mt-4 flex flex-wrap gap-2">
                    <a href="{{ route('posts.create') }}" class="btn-primary">Create Post</a>
                    <a href="{{ route('alerts.create') }}" class="btn-secondary">Create Alert</a>
                </div>
            @else
                <a href="{{ route('register') }}" class="btn-accent mt-4 text-forest-900">Join Sheen Kor</a>
            @endauth
        </section>

        <form method="GET" action="{{ $author ? route('authors.show', $author) : route('posts.index') }}" class="sk-card grid gap-3 p-4 sm:grid-cols-[1fr_auto_auto] sm:items-end">
            <div>
                <label for="story-search" class="text-xs font-semibold uppercase tracking-wide text-gray-400">Search stories</label>
                <input id="story-search" type="search" name="q" value="{{ $search }}" class="sk-input" placeholder="Search by title or content...">
            </div>
            @if(! $author)
                <div>
                    <label for="category-filter" class="text-xs font-semibold uppercase tracking-wide text-gray-400">Category</label>
                    <select id="category-filter" name="category" class="sk-input" onchange="this.form.submit()">
                        <option value="">All categories</option>
                        @foreach($categories as $feedCategory)
                            <option value="{{ $feedCategory->slug }}" @selected($category?->slug === $feedCategory->slug)>{{ $feedCategory->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <button type="submit" class="btn-secondary">Search</button>
        </form>

        @forelse($posts as $index => $post)
            <x-post-card :post="$post" />
            @if((($posts->firstItem() ?? 1) + $index) % 5 === 0)
                <x-in-feed-ad :ad="demo_ads()[($index) % count(demo_ads())]" />
            @endif
        @empty
            <x-empty-state title="No posts yet" :action-label="auth()->check() ? 'Create a post' : 'Join Sheen Kor'" :action-url="auth()->check() ? route('posts.create') : route('register')">
                Be the first to share a photo, story, or idea with the community.
            </x-empty-state>
        @endforelse

        <div>{{ $posts->links() }}</div>
    </div>

    @include('posts.partials.engagement-assets')
</x-app-layout>
