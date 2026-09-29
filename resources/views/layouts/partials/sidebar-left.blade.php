@php
    $homeFeed = request()->routeIs('posts.index');
@endphp

<aside class="{{ $homeFeed ? 'hidden w-60 shrink-0 lg:block xl:w-64' : 'hidden w-56 shrink-0 lg:block xl:w-52' }}">
    <div class="sticky top-20 space-y-6">
        @if($homeFeed)
            <nav class="space-y-1 text-sm font-medium" aria-label="{{ __('Main') }}">
                <a href="{{ route('posts.index') }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 {{ request()->routeIs('posts.index') ? 'bg-forest-50 text-forest-900' : 'text-gray-600 hover:bg-white hover:text-forest-800' }}">
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1v-9.5Z"/></svg>
                    Home
                </a>
                <a href="{{ route('explore.index') }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 {{ request()->routeIs('explore.*') ? 'bg-forest-50 text-forest-900' : 'text-gray-600 hover:bg-white hover:text-forest-800' }}">
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M11 19a8 8 0 1 1 0-16 8 8 0 0 1 0 16Z"/></svg>
                    Explore
                </a>
                <a href="{{ route('alerts.index') }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 {{ request()->routeIs('alerts.*') ? 'bg-forest-50 text-forest-900' : 'text-gray-600 hover:bg-white hover:text-forest-800' }}">
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 1 0-12 0v3.2c0 .5-.2 1-.6 1.4L4 17h5m6 0v1a3 3 0 1 1-6 0v-1m6 0H9"/></svg>
                    Alerts
                </a>
                <a href="{{ route('market.index') }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 {{ request()->routeIs('market.*') ? 'bg-forest-50 text-forest-900' : 'text-gray-600 hover:bg-white hover:text-forest-800' }}">
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 9h18l-1 11H4L3 9Zm3-4h12l1 4H5l1-4Z"/></svg>
                    Market
                </a>
                <a href="{{ route('messages.index') }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 {{ request()->routeIs('messages.*') ? 'bg-forest-50 text-forest-900' : 'text-gray-600 hover:bg-white hover:text-forest-800' }}">
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M7 8h10M7 12h6m8 1a9 9 0 1 1-3.2-6.96L21 3v6h-6"/></svg>
                    <span class="min-w-0 flex-1">Chat</span>
                    <span
                        x-show="$store.notifications.chatUnread > 0"
                        x-cloak
                        class="h-2 w-2 shrink-0 rounded-full bg-lime-500"
                        aria-hidden="true"
                    ></span>
                </a>
                <a href="{{ route('leaderboard.index') }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 {{ request()->routeIs('leaderboard.*') ? 'bg-forest-50 text-forest-900' : 'text-gray-600 hover:bg-white hover:text-forest-800' }}">
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8 21h8M12 17v4M7 4h10v5a5 5 0 0 1-10 0V4Zm10 2h2a3 3 0 0 1 0 6h-2M7 6H5a3 3 0 0 0 0 6h2"/></svg>
                    Scores
                </a>
            </nav>

            <div>
                <p class="px-3 text-xs font-semibold uppercase tracking-wide text-gray-400">Your activity</p>
                <nav class="mt-2 space-y-1 text-sm font-medium" aria-label="{{ __('Your activity') }}">
                    <a href="{{ route('users.show', auth()->user()) }}" class="flex items-center gap-3 rounded-xl px-3 py-2 text-gray-600 hover:bg-white hover:text-forest-800">
                        <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h8M8 12h8M8 17h5M6 3h12a2 2 0 0 1 2 2v14l-4-2-4 2-4-2-4 2V5a2 2 0 0 1 2-2Z"/></svg>
                        My posts
                    </a>
                    <a href="{{ route('users.show', ['user' => auth()->user(), 'tab' => 'alerts']) }}" class="flex items-center gap-3 rounded-xl px-3 py-2 text-gray-600 hover:bg-white hover:text-forest-800">
                        <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.3 4.2 2.6 18a2 2 0 0 0 1.7 3h15.4a2 2 0 0 0 1.7-3L13.7 4.2a2 2 0 0 0-3.4 0Z"/></svg>
                        My alerts
                    </a>
                    <a href="{{ route('market.mine') }}" class="flex items-center gap-3 rounded-xl px-3 py-2 {{ request()->routeIs('market.mine') ? 'bg-forest-50 text-forest-900' : 'text-gray-600 hover:bg-white hover:text-forest-800' }}">
                        <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 7h12l-1 13H7L6 7Zm3 0V5a3 3 0 0 1 6 0v2"/></svg>
                        My market
                    </a>
                </nav>
            </div>

            <div class="space-y-2" x-data>
                <a href="{{ route('posts.create') }}" class="btn-primary w-full" @click.prevent="$dispatch('open-post-composer', { intent: 'text' })">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14"/></svg>
                    Create post
                </a>
                <a href="{{ route('alerts.create') }}" class="btn-secondary w-full">Create alert</a>
                @can('create', App\Models\MarketListing::class)
                    <a href="{{ route('market.create') }}" class="btn-secondary w-full">Create listing</a>
                @endcan
            </div>

            <div class="overflow-hidden rounded-2xl bg-gradient-to-b from-forest-50 to-sand-50 px-4 pb-0 pt-4" aria-hidden="true">
                <p class="text-sm font-medium leading-6 text-forest-800">Small actions create a greener tomorrow</p>
                <svg class="mt-3 h-24 w-full text-forest-700" viewBox="0 0 240 96" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M0 78c28-18 46-18 72-4s40 8 64-6 40-10 64 2 28 8 40 0v26H0V78Z" fill="currentColor" opacity=".18"/>
                    <path d="M0 88c36-10 52-8 84 0s48 6 80-6 44-4 76 4v10H0V88Z" fill="currentColor" opacity=".28"/>
                    <path d="M46 70c8-22 16-22 24 0" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
                    <circle cx="58" cy="42" r="14" fill="currentColor" opacity=".55"/>
                    <path d="M118 74c6-18 14-18 20 0" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
                    <circle cx="128" cy="50" r="11" fill="currentColor" opacity=".4"/>
                    <path d="M176 72c7-20 15-20 22 0" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
                    <circle cx="187" cy="46" r="12" fill="currentColor" opacity=".5"/>
                </svg>
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
