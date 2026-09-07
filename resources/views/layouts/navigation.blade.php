<nav x-data="{ open: false }" class="sticky top-0 z-40 border-b border-gray-100 bg-white/95 backdrop-blur">
    @php($unreadChats = auth()->check() ? auth()->user()->unreadConversationCount() : 0)
    <div class="mx-auto flex h-16 max-w-7xl items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
        <a href="{{ auth()->check() ? route('posts.index') : route('home') }}" class="shrink-0">
            <x-brand />
        </a>

        @auth
            <div class="hidden items-center gap-6 lg:flex">
                <x-nav-link :href="route('posts.index')" :active="request()->routeIs('posts.index', 'posts.show')">{{ __('Home') }}</x-nav-link>
                <x-nav-link :href="route('explore.index')" :active="request()->routeIs('explore.*')">{{ __('Explore') }}</x-nav-link>
                <x-nav-link :href="route('alerts.index')" :active="request()->routeIs('alerts.*')">{{ __('Alerts') }}</x-nav-link>
                <!-- <x-nav-link :href="route('tips.index')" :active="request()->routeIs('tips.*') || (request()->routeIs('categories.show') && request()->route('category')?->slug === 'tips')">{{ __('Tips') }}</x-nav-link> -->
            </div>

            <div class="hidden items-center gap-3 sm:flex">
                <a href="{{ route('explore.index') }}" class="inline-flex h-10 w-10 items-center justify-center rounded-full text-gray-500 hover:bg-sand-50 hover:text-forest-800" aria-label="{{ __('Search') }}">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M11 19a8 8 0 1 1 0-16 8 8 0 0 1 0 16Z"/></svg>
                </a>
                <a href="{{ route('messages.index') }}" class="relative inline-flex h-10 w-10 items-center justify-center rounded-full text-gray-500 hover:bg-sand-50 hover:text-forest-800" aria-label="{{ __('Chat') }}">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M7 8h10M7 12h6m8 1a9 9 0 1 1-3.2-6.96L21 3v6h-6"/></svg>
                    @if($unreadChats > 0)
                        <span class="absolute -right-0.5 -top-0.5 inline-flex min-w-5 items-center justify-center rounded-full bg-lime-400 px-1 text-[10px] font-bold text-forest-900">{{ $unreadChats }}</span>
                    @endif
                </a>
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button type="button" class="inline-flex items-center gap-2 rounded-full p-0.5 hover:bg-sand-50">
                            <x-user-avatar :user="auth()->user()" size="sm" />
                            <span class="hidden text-sm font-medium text-forest-900 md:inline">{{ auth()->user()->name }}</span>
                        </button>
                    </x-slot>
                    <x-slot name="content">
                        <x-dropdown-link :href="route('users.show', auth()->user())">{{ __('My profile') }}</x-dropdown-link>
                        <x-dropdown-link :href="route('profile.edit')">{{ __('Edit profile') }}</x-dropdown-link>
                        <x-dropdown-link :href="route('leaderboard.index')">{{ __('Scores') }}</x-dropdown-link>
                        @can('analytics.view')
                            <x-dropdown-link :href="route('analytics.index')">{{ __('Analytics') }}</x-dropdown-link>
                        @endcan
                        @can('users.manage')
                            <x-dropdown-link :href="route('admin.users.index')">{{ __('Users') }}</x-dropdown-link>
                        @endcan
                        @can('roles.manage')
                            <x-dropdown-link :href="route('admin.roles.index')">{{ __('Roles') }}</x-dropdown-link>
                        @endcan
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <x-dropdown-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').submit();">
                                {{ __('Log Out') }}
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>
        @else
            <div class="hidden items-center gap-6 md:flex">
                <x-nav-link :href="route('home')" :active="request()->routeIs('home')">{{ __('Home') }}</x-nav-link>
                <x-nav-link :href="route('about')" :active="request()->routeIs('about')">{{ __('About') }}</x-nav-link>
                <x-nav-link :href="route('posts.index')" :active="request()->routeIs('posts.*', 'categories.*')">{{ __('Blog') }}</x-nav-link>
                <x-nav-link :href="route('tips.index')" :active="request()->routeIs('tips.*')">{{ __('Tips') }}</x-nav-link>
                <x-nav-link :href="route('alerts.index')" :active="request()->routeIs('alerts.*')">{{ __('Alerts') }}</x-nav-link>
            </div>
            <div class="hidden items-center gap-2 sm:flex">
                <a href="{{ route('login') }}" class="btn-secondary">{{ __('Login') }}</a>
                <a href="{{ route('register') }}" class="btn-accent text-forest-900">{{ __('Sign Up') }}</a>
            </div>
        @endauth

        <button type="button" class="inline-flex rounded-md p-2 text-gray-500 sm:hidden" @click="open = ! open" aria-label="{{ __('Open menu') }}">
            <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                <path :class="{'hidden': open}" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                <path :class="{'hidden': ! open}" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    <div :class="{'block': open, 'hidden': ! open}" class="hidden border-t border-gray-100 sm:hidden">
        <div class="space-y-1 px-4 py-3">
            @auth
                <x-responsive-nav-link :href="route('posts.index')" :active="request()->routeIs('posts.*')">{{ __('Home') }}</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('explore.index')">{{ __('Explore') }}</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('alerts.index')">{{ __('Alerts') }}</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('tips.index')">{{ __('Tips') }}</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('messages.index')">{{ __('Chat') }} @if($unreadChats > 0) ({{ $unreadChats }}) @endif</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('users.show', auth()->user())">{{ __('My profile') }}</x-responsive-nav-link>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <x-responsive-nav-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').submit();">{{ __('Log Out') }}</x-responsive-nav-link>
                </form>
            @else
                <x-responsive-nav-link :href="route('home')">{{ __('Home') }}</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('about')">{{ __('About') }}</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('posts.index')">{{ __('Blog') }}</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('tips.index')">{{ __('Tips') }}</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('alerts.index')">{{ __('Alerts') }}</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('login')">{{ __('Login') }}</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('register')">{{ __('Sign Up') }}</x-responsive-nav-link>
            @endauth
        </div>
    </div>
</nav>
