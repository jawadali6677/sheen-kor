@php
    $unreadChats = 0;
    $unreadNotifications = 0;
    $notificationConfig = [];

    if (auth()->check()) {
        $unreadChats = auth()->user()->unreadConversationCount();
        $unreadNotifications = auth()->user()->unreadNotifications()->count();
        $notificationConfig = [
            'userId' => auth()->id(),
            'unread' => $unreadNotifications,
            'chatUnread' => $unreadChats,
            'items' => auth()->user()->notificationInbox(),
            'markReadTemplate' => url('/notifications/__ID__/read'),
            'markAllUrl' => route('notifications.read-all'),
            'csrfToken' => csrf_token(),
        ];
    }
@endphp
@php
    $homeNav = auth()->check() && request()->routeIs('posts.index');
@endphp
<nav x-data="{ open: false }" @if(auth()->check()) x-init="$store.notifications.boot(@js($notificationConfig))" @endif class="sticky top-0 z-40 border-b border-gray-100 bg-white/95 backdrop-blur">
    <div class="app-shell flex h-16 items-center justify-between gap-4 {{ $homeNav ? 'lg:h-[4.5rem]' : '' }}">
        <a href="{{ auth()->check() ? route('posts.index') : route('home') }}" class="shrink-0 {{ $homeNav ? 'lg:inline-flex lg:flex-col lg:justify-center' : '' }}">
            <x-brand />
            @if($homeNav)
                <span class="mt-0.5 hidden text-[11px] font-medium tracking-wide text-gray-500 lg:block">Greener Community · Stronger Together</span>
            @endif
        </a>

        @auth
            @if($homeNav)
                <form method="GET" action="{{ route('explore.index') }}" class="mx-4 hidden min-w-0 max-w-xl flex-1 lg:block">
                    <label for="home-search" class="sr-only">{{ __('Search posts, people, places') }}</label>
                    <div class="relative">
                        <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M11 19a8 8 0 1 1 0-16 8 8 0 0 1 0 16Z"/></svg>
                        <input id="home-search" type="search" name="q" placeholder="Search posts, people, places..." class="w-full rounded-full border-gray-200 bg-sand-50 py-2 pl-10 pr-4 text-sm text-forest-900 placeholder:text-gray-400 focus:border-forest-600 focus:ring-forest-600">
                    </div>
                </form>
            @else
                <div class="hidden items-center gap-6 lg:flex">
                    <x-nav-link :href="route('posts.index')" :active="request()->routeIs('posts.index', 'posts.show')">{{ __('Home') }}</x-nav-link>
                    <x-nav-link :href="route('explore.index')" :active="request()->routeIs('explore.*')">{{ __('Explore') }}</x-nav-link>
                    <x-nav-link :href="route('alerts.index')" :active="request()->routeIs('alerts.*')">{{ __('Alerts') }}</x-nav-link>
                    <x-nav-link :href="route('market.index')" :active="request()->routeIs('market.*')">{{ __('Market') }}</x-nav-link>
                    <!-- <x-nav-link :href="route('tips.index')" :active="request()->routeIs('tips.*') || (request()->routeIs('categories.show') && request()->route('category')?->slug === 'tips')">{{ __('Tips') }}</x-nav-link> -->
                </div>
            @endif

            <div class="hidden items-center gap-3 sm:flex">
                <a href="{{ route('explore.index') }}" class="inline-flex h-10 w-10 items-center justify-center rounded-full text-gray-500 hover:bg-sand-50 hover:text-forest-800 {{ $homeNav ? 'lg:hidden' : '' }}" aria-label="{{ __('Search') }}">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M11 19a8 8 0 1 1 0-16 8 8 0 0 1 0 16Z"/></svg>
                </a>
                <a href="{{ route('messages.index') }}" class="relative inline-flex h-10 w-10 items-center justify-center rounded-full text-gray-500 hover:bg-sand-50 hover:text-forest-800 {{ $homeNav ? 'lg:order-2' : '' }}" aria-label="{{ __('Chat') }}">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M7 8h10M7 12h6m8 1a9 9 0 1 1-3.2-6.96L21 3v6h-6"/></svg>
                    <span
                        x-show="$store.notifications.chatUnread > 0"
                        x-cloak
                        x-text="$store.notifications.chatUnread"
                        class="absolute -right-0.5 -top-0.5 inline-flex min-w-5 items-center justify-center rounded-full bg-lime-400 px-1 text-[10px] font-bold text-forest-900"
                    >{{ $unreadChats }}</span>
                </a>
                <div class="{{ $homeNav ? 'lg:order-1' : '' }}">
                    @include('layouts.partials.notification-bell')
                </div>
                <div class="{{ $homeNav ? 'lg:order-3' : '' }}">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button type="button" class="inline-flex items-center gap-2 rounded-full p-0.5 hover:bg-sand-50">
                            <x-user-avatar :user="auth()->user()" size="sm" />
                            <span class="hidden text-sm font-medium text-forest-900 md:inline">{{ auth()->user()->name }}</span>
                        </button>
                    </x-slot>
                    <x-slot name="content">
                        <x-dropdown-link :href="route('users.show', auth()->user())">{{ __('My profile') }}</x-dropdown-link>
                        <x-dropdown-link :href="route('market.mine')">{{ __('My Market') }}</x-dropdown-link>
                        <x-dropdown-link :href="route('orders.index')">{{ __('My Orders') }}</x-dropdown-link>
                        <x-dropdown-link :href="route('profile.edit')">{{ __('Edit profile') }}</x-dropdown-link>
                        <x-dropdown-link :href="route('leaderboard.index')">{{ __('Scores') }}</x-dropdown-link>
                        @can('posts.moderate')
                            <x-dropdown-link :href="route('admin.posts.index', ['status' => 'pending'])">{{ __('Review stories') }}</x-dropdown-link>
                        @endcan
                        @can('market.moderate')
                            <x-dropdown-link :href="route('admin.market.index', ['status' => 'pending'])">{{ __('Review market') }}</x-dropdown-link>
                        @endcan
                        @can('analytics.view')
                            <x-dropdown-link :href="route('analytics.index')">{{ __('Analytics') }}</x-dropdown-link>
                        @endcan
                        @can('users.manage')
                            <x-dropdown-link :href="route('admin.users.index')">{{ __('Users') }}</x-dropdown-link>
                        @endcan
                        @can('roles.manage')
                            <x-dropdown-link :href="route('admin.roles.index')">{{ __('Roles') }}</x-dropdown-link>
                        @endcan
                        @can('monetization.manage')
                            <x-dropdown-link :href="route('admin.monetization.index')">{{ __('Monetization') }}</x-dropdown-link>
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
            </div>
        @else
            <div class="hidden items-center gap-6 md:flex">
                <x-nav-link :href="route('home')" :active="request()->routeIs('home')">{{ __('Home') }}</x-nav-link>
                <x-nav-link :href="route('about')" :active="request()->routeIs('about')">{{ __('About') }}</x-nav-link>
                <x-nav-link :href="route('posts.index')" :active="request()->routeIs('posts.*', 'categories.*')">{{ __('Blog') }}</x-nav-link>
                <x-nav-link :href="route('tips.index')" :active="request()->routeIs('tips.*')">{{ __('Tips') }}</x-nav-link>
                <x-nav-link :href="route('alerts.index')" :active="request()->routeIs('alerts.*')">{{ __('Alerts') }}</x-nav-link>
                <x-nav-link :href="route('market.index')" :active="request()->routeIs('market.*')">{{ __('Market') }}</x-nav-link>
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
                <x-responsive-nav-link :href="route('market.index')">{{ __('Market') }}</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('tips.index')">{{ __('Tips') }}</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('messages.index')">{{ __('Chat') }} <span x-show="$store.notifications.chatUnread > 0" x-cloak x-text="'(' + $store.notifications.chatUnread + ')'">@if($unreadChats > 0) ({{ $unreadChats }}) @endif</span></x-responsive-nav-link>
                <x-responsive-nav-link :href="route('users.show', auth()->user())">{{ __('My profile') }}</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('market.mine')">{{ __('My Market') }}</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('orders.index')">{{ __('My Orders') }}</x-responsive-nav-link>
                @can('posts.moderate')
                    <x-responsive-nav-link :href="route('admin.posts.index', ['status' => 'pending'])">{{ __('Review stories') }}</x-responsive-nav-link>
                @endcan
                @can('market.moderate')
                    <x-responsive-nav-link :href="route('admin.market.index', ['status' => 'pending'])">{{ __('Review market') }}</x-responsive-nav-link>
                @endcan
                @can('monetization.manage')
                    <x-responsive-nav-link :href="route('admin.monetization.index')">{{ __('Monetization') }}</x-responsive-nav-link>
                @endcan
                <div class="px-3 py-2">
                    @include('layouts.partials.notification-bell')
                </div>
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
                <x-responsive-nav-link :href="route('market.index')">{{ __('Market') }}</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('login')">{{ __('Login') }}</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('register')">{{ __('Sign Up') }}</x-responsive-nav-link>
            @endauth
        </div>
    </div>
</nav>
