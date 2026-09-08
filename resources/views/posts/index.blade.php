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

        <div
            x-data="infiniteFeed({
                nextUrl: @js($posts->nextPageUrl()),
                finishedText: 'No more posts',
            })"
        >
            <div x-ref="items" class="space-y-4">
                @include('posts.partials.feed-items')
            </div>
            <div x-ref="sentinel" class="h-8"></div>
            <p class="py-4 text-center text-sm text-gray-500" x-show="loading" x-cloak>Loading...</p>
            <p class="py-4 text-center text-sm text-gray-500" x-show="finished && ! loading && {{ $posts->total() > 0 ? 'true' : 'false' }}" x-cloak>No more posts</p>
        </div>
    </div>

    @include('posts.partials.engagement-assets')
</x-app-layout>
