<x-app-layout>
    <div class="mx-auto max-w-xl space-y-4 lg:max-w-2xl xl:max-w-3xl {{ auth()->check() && request()->routeIs('posts.index') ? 'lg:mx-0 lg:max-w-none xl:max-w-none' : '' }}">
        <x-flash />

        @if($posts->currentPage() === 1)
            @auth
                @php
                    $feedHour = now()->hour;
                    $feedGreeting = match (true) {
                        $feedHour < 12 => 'Good morning',
                        $feedHour < 17 => 'Good afternoon',
                        default => 'Good evening',
                    };
                    $feedFirstName = \Illuminate\Support\Str::before(auth()->user()->name, ' ');
                @endphp
                <section class="sk-card relative p-4 lg:bg-gradient-to-br lg:from-white lg:via-white lg:to-forest-50 lg:p-5" data-feed-composer-bar x-data>
                    <div class="pointer-events-none absolute right-3 top-3 hidden text-forest-200 xl:block" aria-hidden="true">
                        <svg class="h-16 w-24" viewBox="0 0 96 64" fill="currentColor">
                            <path d="M8 52c10-16 18-16 28 0 8-18 18-22 30-8 6 8 14 10 22 6v14H8V52Z" opacity=".9"/>
                            <circle cx="28" cy="28" r="10"/>
                            <circle cx="58" cy="22" r="12" opacity=".75"/>
                        </svg>
                    </div>
                    <div class="relative mb-4 hidden items-center justify-between gap-4 lg:flex">
                        <div class="flex min-w-0 items-center gap-3">
                            <x-user-avatar :user="auth()->user()" size="sm" class="lg:h-11 lg:w-11" />
                            <div class="min-w-0">
                                <p class="truncate text-base font-semibold text-forest-900">
                                    {{ $feedGreeting }}, {{ $feedFirstName }}
                                    <svg class="ms-1 inline h-4 w-4 text-forest-600" viewBox="0 0 32 32" fill="currentColor" aria-hidden="true"><path d="M16 3c.6 4.2 2.4 7.4 6.4 10.2-3.1.6-5.3 2.1-6.4 5.3-1.1-3.2-3.3-4.7-6.4-5.3C13.6 10.4 15.4 7.2 16 3Z"/><path d="M16 13.8c4.6 1.8 7.7 5 8.8 10.2-3.7-1.2-6.4-.6-8.8 2.4-2.4-3-5.1-3.6-8.8-2.4 1.1-5.2 4.2-8.4 8.8-10.2Z" opacity=".85"/></svg>
                                </p>
                                <p class="text-sm text-gray-500">Share something green and inspire your community.</p>
                            </div>
                        </div>
                        <p class="hidden max-w-[9rem] text-right text-xs leading-5 text-forest-700 xl:block">Together for a cleaner, greener future</p>
                    </div>
                    <div class="flex items-center gap-3 lg:hidden">
                        <x-user-avatar :user="auth()->user()" size="sm" />
                        <a
                            href="{{ route('posts.create') }}"
                            class="flex min-h-12 flex-1 items-center rounded-full bg-sand-50 px-4 text-base text-gray-500"
                            @click.prevent="$dispatch('open-post-composer', { intent: 'text' })"
                        >What's on your mind?</a>
                    </div>
                    <a
                        href="{{ route('posts.create') }}"
                        class="hidden min-h-12 w-full items-center rounded-2xl bg-sand-50 px-4 text-base text-gray-500 lg:flex"
                        @click.prevent="$dispatch('open-post-composer', { intent: 'text' })"
                    >What's on your mind?</a>
                    <div class="mt-3 grid grid-cols-3 gap-2 lg:flex lg:flex-wrap">
                        <a href="{{ route('posts.create') }}" class="flex min-h-[4.5rem] flex-col items-center justify-center gap-1 rounded-2xl bg-sand-50 px-2 py-3 text-sm font-semibold text-forest-800 transition duration-150 hover:bg-forest-50 lg:min-h-0 lg:flex-row lg:gap-2 lg:rounded-full lg:border lg:border-emerald-100 lg:bg-emerald-50 lg:px-3 lg:py-2 lg:text-emerald-900 lg:hover:bg-emerald-100" @click.prevent="$dispatch('open-post-composer', { intent: 'photo' })">
                            <svg class="h-7 w-7 text-forest-700 lg:h-4 lg:w-4 lg:text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m4 16 4.6-4.6a2 2 0 0 1 2.8 0L16 16m-2-2 1.6-1.6a2 2 0 0 1 2.8 0L20 14M8 8h.01M6 20h12a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2Z"/></svg>
                            Photo
                        </a>
                        <a href="{{ route('posts.create') }}" class="flex min-h-[4.5rem] flex-col items-center justify-center gap-1 rounded-2xl bg-sand-50 px-2 py-3 text-sm font-semibold text-forest-800 transition duration-150 hover:bg-forest-50 lg:min-h-0 lg:flex-row lg:gap-2 lg:rounded-full lg:border lg:border-purple-100 lg:bg-purple-50 lg:px-3 lg:py-2 lg:text-purple-900 lg:hover:bg-purple-100" @click.prevent="$dispatch('open-post-composer', { intent: 'video' })">
                            <svg class="h-7 w-7 text-forest-700 lg:h-4 lg:w-4 lg:text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m15 10 4.6-2.3A1 1 0 0 1 21 8.6v6.8a1 1 0 0 1-1.4.9L15 14M4 8h8a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2v-4a2 2 0 0 1 2-2Z"/></svg>
                            Video
                        </a>
                        <a href="{{ route('posts.create') }}" class="flex min-h-[4.5rem] flex-col items-center justify-center gap-1 rounded-2xl bg-sand-50 px-2 py-3 text-sm font-semibold text-forest-800 transition duration-150 hover:bg-forest-50 lg:min-h-0 lg:flex-row lg:gap-2 lg:rounded-full lg:border lg:border-sky-100 lg:bg-sky-50 lg:px-3 lg:py-2 lg:text-sky-900 lg:hover:bg-sky-100" @click.prevent="$dispatch('open-post-composer', { intent: 'camera' })">
                            <svg class="h-7 w-7 text-forest-700 lg:h-4 lg:w-4 lg:text-sky-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 8h2.5l1.2-2h8.6l1.2 2H20a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2v-8a2 2 0 0 1 2-2Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"/></svg>
                            Camera
                        </a>
                        <a href="{{ route('posts.create') }}" class="hidden items-center gap-2 rounded-full border border-amber-100 bg-amber-50 px-3 py-2 text-sm font-semibold text-amber-900 transition duration-150 hover:bg-amber-100 lg:inline-flex" @click.prevent="$dispatch('open-post-composer', { intent: 'text' })">
                            <svg class="h-4 w-4 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h8M8 12h8M8 17h5"/></svg>
                            Text
                        </a>
                        <a href="{{ route('alerts.create') }}" class="hidden items-center gap-2 rounded-full border border-red-100 bg-red-50 px-3 py-2 text-sm font-semibold text-red-700 transition duration-150 hover:bg-red-100 lg:inline-flex">
                            <svg class="h-4 w-4 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.3 4.2 2.6 18a2 2 0 0 0 1.7 3h15.4a2 2 0 0 0 1.7-3L13.7 4.2a2 2 0 0 0-3.4 0Z"/></svg>
                            Create Alert
                        </a>
                    </div>
                    <div class="mt-3 text-right lg:hidden">
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

        <div
            id="feed-post-status"
            class="sk-card p-4"
            x-data
            x-cloak
            x-show="$store.feedPosting.visible"
            :hidden="! $store.feedPosting.visible"
            :role="$store.feedPosting.phase === 'rejected' ? 'alert' : 'status'"
            aria-live="polite"
            data-feed-post-status
            data-status-url="{{ session('checking_post_id') ? route('posts.moderation-status', session('checking_post_id')) : '' }}"
            x-init="if ($el.dataset.statusUrl) $store.feedPosting.watch($el.dataset.statusUrl)"
        >
            <p
                class="text-base font-semibold text-forest-900"
                :class="$store.feedPosting.phase === 'rejected' ? 'text-red-700' : 'text-forest-900'"
                data-feed-post-status-message
            >
                <span x-text="$store.feedPosting.message">@if(session('checking_post_id'))Checking your post...@endif</span>
            </p>
            <div
                class="mt-3 h-2.5 overflow-hidden rounded-full bg-sand-100"
                x-show="$store.feedPosting.phase === 'uploading' || $store.feedPosting.phase === 'checking'"
            >
                <div
                    class="h-full rounded-full bg-lime-400 transition-all duration-150"
                    :class="$store.feedPosting.phase === 'checking' ? 'animate-pulse' : ''"
                    :style="$store.feedPosting.phase === 'uploading' ? `width: ${Math.max($store.feedPosting.progress, 8)}%` : 'width: 100%'"
                ></div>
            </div>
        </div>

        @php
            $feedHomeUrl = $author ? route('authors.show', $author) : route('posts.index');
            $allStoriesUrl = $search ? $feedHomeUrl.'?'.http_build_query(['q' => $search]) : $feedHomeUrl;
        @endphp
        <form method="GET" action="{{ $feedHomeUrl }}" class="sk-card p-4 lg:flex lg:flex-wrap lg:items-center lg:justify-between lg:gap-3 lg:p-3">
            @unless($author)
                <div class="hidden items-center gap-1 lg:flex">
                    <a href="{{ $allStoriesUrl }}" class="rounded-full px-3 py-1.5 text-sm font-semibold transition duration-150 {{ $category ? 'text-gray-600 hover:bg-forest-50 hover:text-forest-800' : 'bg-forest-800 text-white shadow-sm' }}">All</a>
                    <a href="{{ route('alerts.index') }}" class="rounded-full px-3 py-1.5 text-sm font-semibold text-gray-600 transition duration-150 hover:bg-forest-50 hover:text-forest-800">Alerts</a>
                    <a href="{{ route('market.index') }}" class="rounded-full px-3 py-1.5 text-sm font-semibold text-gray-600 transition duration-150 hover:bg-forest-50 hover:text-forest-800">Market</a>
                </div>
            @endunless
            <div class="grid gap-3 sm:grid-cols-[1fr_auto_auto] sm:items-end lg:flex lg:items-center lg:gap-2">
                <div class="lg:w-44">
                    <label for="story-search" class="text-xs font-semibold uppercase tracking-wide text-gray-400 lg:sr-only">Search stories</label>
                    <input id="story-search" type="search" name="q" value="{{ $search }}" class="sk-input lg:mt-0" placeholder="Search by title or content...">
                </div>
                @if(! $author)
                    <div class="lg:w-44">
                        <label for="category-filter" class="text-xs font-semibold uppercase tracking-wide text-gray-400 lg:sr-only">Category</label>
                        <select id="category-filter" name="category" class="sk-input lg:mt-0" onchange="this.form.submit()">
                            <option value="">All categories</option>
                            @foreach($categories as $feedCategory)
                                <option value="{{ $feedCategory->slug }}" @selected($category?->slug === $feedCategory->slug)>{{ $feedCategory->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <button type="submit" class="btn-secondary lg:shrink-0 lg:border-forest-800 lg:bg-forest-800 lg:text-white lg:hover:border-forest-700 lg:hover:bg-forest-700">Search</button>
            </div>
        </form>

        <div
            x-data="infiniteFeed({
                nextUrl: @js($posts->nextPageUrl()),
                finishedText: 'No more posts',
            })"
        >
            <div
                x-ref="items"
                id="feed-items"
                class="space-y-4"
                @if(! $author && ! $category && $search === null && $posts->currentPage() === 1)
                    data-live-feed="1"
                @endif
            >
                @include('posts.partials.feed-items')
            </div>
            <div x-ref="sentinel" class="h-8"></div>
            <p class="py-4 text-center text-sm text-gray-500" x-show="loading" x-cloak>Loading...</p>
            <p class="py-4 text-center text-sm text-gray-500" x-show="finished && ! loading && {{ $posts->total() > 0 ? 'true' : 'false' }}" x-cloak>No more posts</p>
        </div>
    </div>

    @include('posts.partials.engagement-assets')
</x-app-layout>
