<x-app-layout>
    <div class="mx-auto max-w-xl space-y-4 lg:max-w-2xl xl:max-w-3xl">
        <x-flash />

        @if(session('checking_post_id'))
            <div
                x-data="postModerationWatch(@js(['url' => route('posts.moderation-status', session('checking_post_id'))]))"
                x-init="start()"
            ></div>
        @endif

        @if($posts->currentPage() === 1)
            @auth
                <section class="sk-card p-4" data-feed-composer-bar x-data>
                    <div class="flex items-center gap-3">
                        <x-user-avatar :user="auth()->user()" size="sm" />
                        <a
                            href="{{ route('posts.create') }}"
                            class="flex min-h-12 flex-1 items-center rounded-full bg-sand-50 px-4 text-base text-gray-500"
                            @click.prevent="$dispatch('open-post-composer', { intent: 'text' })"
                        >What's on your mind?</a>
                    </div>
                    <div class="mt-3 grid grid-cols-3 gap-2">
                        <a href="{{ route('posts.create') }}" class="flex min-h-[4.5rem] flex-col items-center justify-center gap-1 rounded-2xl bg-sand-50 px-2 py-3 text-sm font-semibold text-forest-800" @click.prevent="$dispatch('open-post-composer', { intent: 'photo' })">
                            <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m4 16 4.6-4.6a2 2 0 0 1 2.8 0L16 16m-2-2 1.6-1.6a2 2 0 0 1 2.8 0L20 14M8 8h.01M6 20h12a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2Z"/></svg>
                            Photo
                        </a>
                        <a href="{{ route('posts.create') }}" class="flex min-h-[4.5rem] flex-col items-center justify-center gap-1 rounded-2xl bg-sand-50 px-2 py-3 text-sm font-semibold text-forest-800" @click.prevent="$dispatch('open-post-composer', { intent: 'video' })">
                            <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m15 10 4.6-2.3A1 1 0 0 1 21 8.6v6.8a1 1 0 0 1-1.4.9L15 14M4 8h8a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2v-4a2 2 0 0 1 2-2Z"/></svg>
                            Video
                        </a>
                        <a href="{{ route('posts.create') }}" class="flex min-h-[4.5rem] flex-col items-center justify-center gap-1 rounded-2xl bg-sand-50 px-2 py-3 text-sm font-semibold text-forest-800" @click.prevent="$dispatch('open-post-composer', { intent: 'camera' })">
                            <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 8h2.5l1.2-2h8.6l1.2 2H20a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2v-8a2 2 0 0 1 2-2Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"/></svg>
                            Camera
                        </a>
                    </div>
                    <div class="mt-3 text-right">
                        <a href="{{ route('alerts.create') }}" class="text-sm font-semibold text-forest-800">Create Alert</a>
                    </div>
                </section>
            @else
                <section class="sk-card p-5">
                    <h1 class="text-lg font-semibold text-forest-900">Stories from your community</h1>
                    <p class="mt-1 text-sm text-gray-500">Join to share a photo or a few words.</p>
                    <a href="{{ route('register') }}" class="btn-accent mt-4 text-forest-900">Join Sheen Kor</a>
                </section>
            @endauth
        @endif

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
            <div x-ref="items" id="feed-items" class="space-y-4">
                @include('posts.partials.feed-items')
            </div>
            <div x-ref="sentinel" class="h-8"></div>
            <p class="py-4 text-center text-sm text-gray-500" x-show="loading" x-cloak>Loading...</p>
            <p class="py-4 text-center text-sm text-gray-500" x-show="finished && ! loading && {{ $posts->total() > 0 ? 'true' : 'false' }}" x-cloak>No more posts</p>
        </div>
    </div>

    @include('posts.partials.engagement-assets')
</x-app-layout>
