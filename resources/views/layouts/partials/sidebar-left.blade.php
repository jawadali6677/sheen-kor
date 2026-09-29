@php
    $homeFeed = request()->routeIs('posts.index');
@endphp

<aside class="{{ $homeFeed ? 'hidden w-60 shrink-0 lg:block xl:w-64' : 'hidden w-56 shrink-0 lg:block xl:w-52' }}">
    <div class="sticky top-20 {{ $homeFeed ? '' : 'space-y-6' }}">
        @if($homeFeed)
            <div class="space-y-5 rounded-3xl bg-emerald-50 p-3" data-home-sidebar>
            <nav class="space-y-1 text-sm font-medium" aria-label="{{ __('Main') }}">
                <a href="{{ route('posts.index') }}" class="flex items-center gap-3 rounded-xl bg-emerald-100 px-3 py-2.5 text-forest-900 shadow-sm">
                    <span class="inline-flex h-7 w-7 shrink-0 items-center justify-center text-forest-800">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1v-9.5Z"/></svg>
                    </span>
                    Home
                </a>
                <a href="{{ route('explore.index') }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-gray-600 hover:bg-white/80 hover:text-forest-800">
                    <span class="inline-flex h-7 w-7 shrink-0 items-center justify-center text-forest-600">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M11 19a8 8 0 1 1 0-16 8 8 0 0 1 0 16Z"/></svg>
                    </span>
                    Explore
                </a>
                <a href="{{ route('alerts.index') }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-gray-600 hover:bg-white/80 hover:text-forest-800">
                    <span class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-red-100 text-red-600">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 1 0-12 0v3.2c0 .5-.2 1-.6 1.4L4 17h5m6 0v1a3 3 0 1 1-6 0v-1m6 0H9"/></svg>
                    </span>
                    Alerts
                </a>
                <a href="{{ route('market.index') }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-gray-600 hover:bg-white/80 hover:text-forest-800">
                    <span class="inline-flex h-7 w-7 shrink-0 items-center justify-center text-forest-600">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 9h18l-1 11H4L3 9Zm3-4h12l1 4H5l1-4Z"/></svg>
                    </span>
                    Market
                </a>
                <a href="{{ route('messages.index') }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-gray-600 hover:bg-white/80 hover:text-forest-800">
                    <span class="inline-flex h-7 w-7 shrink-0 items-center justify-center text-emerald-600">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M7 8h10M7 12h6m8 1a9 9 0 1 1-3.2-6.96L21 3v6h-6"/></svg>
                    </span>
                    <span class="min-w-0 flex-1">Chat</span>
                    <span
                        x-show="$store.notifications.chatUnread > 0"
                        x-cloak
                        class="h-2.5 w-2.5 shrink-0 rounded-full bg-emerald-500"
                        aria-hidden="true"
                    ></span>
                </a>
                <a href="{{ route('leaderboard.index') }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-gray-600 hover:bg-white/80 hover:text-forest-800">
                    <span class="inline-flex h-7 w-7 shrink-0 items-center justify-center text-amber-500">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8 21h8M12 17v4M7 4h10v5a5 5 0 0 1-10 0V4Zm10 2h2a3 3 0 0 1 0 6h-2M7 6H5a3 3 0 0 0 0 6h2"/></svg>
                    </span>
                    Scores
                </a>
            </nav>

            <div>
                <p class="px-3 text-xs font-semibold uppercase tracking-wide text-forest-400">Your activity</p>
                <nav class="mt-2 space-y-1 text-sm font-medium" aria-label="{{ __('Your activity') }}">
                    <a href="{{ route('users.show', auth()->user()) }}" class="flex items-center gap-3 rounded-xl px-3 py-2 text-gray-600 hover:bg-white/80 hover:text-forest-800">
                        <svg class="h-5 w-5 shrink-0 text-forest-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h8M8 12h8M8 17h5M6 3h12a2 2 0 0 1 2 2v14l-4-2-4 2-4-2-4 2V5a2 2 0 0 1 2-2Z"/></svg>
                        My posts
                    </a>
                    <a href="{{ route('users.show', ['user' => auth()->user(), 'tab' => 'alerts']) }}" class="flex items-center gap-3 rounded-xl px-3 py-2 text-gray-600 hover:bg-white/80 hover:text-forest-800">
                        <svg class="h-5 w-5 shrink-0 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.3 4.2 2.6 18a2 2 0 0 0 1.7 3h15.4a2 2 0 0 0 1.7-3L13.7 4.2a2 2 0 0 0-3.4 0Z"/></svg>
                        My alerts
                    </a>
                    <a href="{{ route('market.mine') }}" class="flex items-center gap-3 rounded-xl px-3 py-2 text-gray-600 hover:bg-white/80 hover:text-forest-800">
                        <svg class="h-5 w-5 shrink-0 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 7h12l-1 13H7L6 7Zm3 0V5a3 3 0 0 1 6 0v2"/></svg>
                        My market
                    </a>
                </nav>
            </div>

            <div class="space-y-2" x-data>
                <a href="{{ route('posts.create') }}" class="btn-primary w-full" @click.prevent="$dispatch('open-post-composer', { intent: 'text' })">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14"/></svg>
                    Create post
                </a>
                <a href="{{ route('alerts.create') }}" class="inline-flex w-full items-center justify-center gap-2 rounded-full border border-forest-600 bg-white px-5 py-2.5 text-sm font-semibold text-forest-800 transition duration-150 hover:bg-forest-50">
                    <svg class="h-4 w-4 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.3 4.2 2.6 18a2 2 0 0 0 1.7 3h15.4a2 2 0 0 0 1.7-3L13.7 4.2a2 2 0 0 0-3.4 0Z"/></svg>
                    Create alert
                </a>
                @can('create', App\Models\MarketListing::class)
                    <a href="{{ route('market.create') }}" class="inline-flex w-full items-center justify-center gap-2 rounded-full border border-forest-600 bg-white px-5 py-2.5 text-sm font-semibold text-forest-800 transition duration-150 hover:bg-forest-50">
                        <svg class="h-4 w-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 7h12l-1 13H7L6 7Zm3 0V5a3 3 0 0 1 6 0v2"/></svg>
                        Create listing
                    </a>
                @endcan
            </div>

            <div class="-mx-3 -mb-3 overflow-hidden px-4 pt-1" aria-hidden="true">
                <p class="px-1 text-sm font-medium leading-6 text-forest-800">Small actions create a greener tomorrow</p>
                <svg class="mt-2 h-28 w-full" viewBox="0 0 240 112" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="188" cy="26" r="10" class="fill-lime-300"/>
                    <path d="M0 70c28-22 52-20 80-4 24 14 40 8 62-8 20-14 42-12 62 6 12 10 24 12 36 8v40H0V70Z" class="fill-forest-300"/>
                    <path d="M0 88c32-14 54-8 86 2 28 8 44 2 70-10 22-10 46-6 84 10v22H0V88Z" class="fill-forest-600"/>
                    <path d="M118 112c6-22 16-34 26-34 8 0 16 10 20 34" class="stroke-lime-100" stroke-width="8" stroke-linecap="round"/>
                    <path d="M42 86c8-20 16-20 24 0" class="stroke-forest-800" stroke-width="2.5" stroke-linecap="round"/>
                    <circle cx="54" cy="62" r="13" class="fill-forest-700"/>
                    <circle cx="44" cy="70" r="9" class="fill-forest-500"/>
                    <path d="M156 90c7-16 14-16 20 0" class="stroke-forest-900" stroke-width="2.5" stroke-linecap="round"/>
                    <circle cx="166" cy="70" r="11" class="fill-forest-800"/>
                    <circle cx="158" cy="76" r="8" class="fill-emerald-700"/>
                    <path d="M196 92c6-14 12-14 18 0" class="stroke-forest-800" stroke-width="2" stroke-linecap="round"/>
                    <circle cx="205" cy="76" r="9" class="fill-forest-600"/>
                </svg>
            </div>
            </div>
        @else
            <nav class="space-y-1 text-sm font-medium">
                <a href="{{ route('posts.index') }}" class="flex items-center gap-3 rounded-xl px-3 py-2 {{ request()->routeIs('posts.index') ? 'bg-forest-50 text-forest-900' : 'text-gray-600 hover:bg-white hover:text-forest-800' }}">Home</a>
                <a href="{{ route('explore.index') }}" class="flex items-center gap-3 rounded-xl px-3 py-2 {{ request()->routeIs('explore.*') ? 'bg-forest-50 text-forest-900' : 'text-gray-600 hover:bg-white hover:text-forest-800' }}">Explore</a>
                <a href="{{ route('alerts.index') }}" class="flex items-center gap-3 rounded-xl px-3 py-2 {{ request()->routeIs('alerts.*') ? 'bg-forest-50 text-forest-900' : 'text-gray-600 hover:bg-white hover:text-forest-800' }}">Alerts</a>
                <a href="{{ route('market.index') }}" class="flex items-center gap-3 rounded-xl px-3 py-2 {{ request()->routeIs('market.*') ? 'bg-forest-50 text-forest-900' : 'text-gray-600 hover:bg-white hover:text-forest-800' }}">Market</a>
                <!-- <a href="{{ route('tips.index') }}" class="flex items-center gap-3 rounded-xl px-3 py-2 {{ request()->routeIs('tips.*') ? 'bg-forest-50 text-forest-900' : 'text-gray-600 hover:bg-white hover:text-forest-800' }}">Tips</a> -->
                <a href="{{ route('messages.index') }}" class="flex items-center gap-3 rounded-xl px-3 py-2 {{ request()->routeIs('messages.*') ? 'bg-forest-50 text-forest-900' : 'text-gray-600 hover:bg-white hover:text-forest-800' }}">Chat</a>
                <a href="{{ route('leaderboard.index') }}" class="flex items-center gap-3 rounded-xl px-3 py-2 {{ request()->routeIs('leaderboard.*') ? 'bg-forest-50 text-forest-900' : 'text-gray-600 hover:bg-white hover:text-forest-800' }}">Scores</a>
            </nav>

            <div class="space-y-2" x-data>
                <a href="{{ route('posts.create') }}" class="btn-primary w-full" @click.prevent="$dispatch('open-post-composer', { intent: 'text' })">Create post</a>
                <a href="{{ route('alerts.create') }}" class="btn-secondary w-full">Create alert</a>
                @can('create', App\Models\MarketListing::class)
                    <a href="{{ route('market.create') }}" class="btn-secondary w-full">Create listing</a>
                @endcan
            </div>
        @endif
    </div>
</aside>
