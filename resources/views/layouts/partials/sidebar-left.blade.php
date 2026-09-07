@php
    $createOpen = false;
@endphp

<aside class="hidden w-56 shrink-0 lg:block">
    <div class="sticky top-20 space-y-6">
        <nav class="space-y-1 text-sm font-medium">
            <a href="{{ route('posts.index') }}" class="flex items-center gap-3 rounded-xl px-3 py-2 {{ request()->routeIs('posts.index') ? 'bg-forest-50 text-forest-900' : 'text-gray-600 hover:bg-white hover:text-forest-800' }}">Home</a>
            <a href="{{ route('explore.index') }}" class="flex items-center gap-3 rounded-xl px-3 py-2 {{ request()->routeIs('explore.*') ? 'bg-forest-50 text-forest-900' : 'text-gray-600 hover:bg-white hover:text-forest-800' }}">Explore</a>
            <a href="{{ route('alerts.index') }}" class="flex items-center gap-3 rounded-xl px-3 py-2 {{ request()->routeIs('alerts.*') ? 'bg-forest-50 text-forest-900' : 'text-gray-600 hover:bg-white hover:text-forest-800' }}">Alerts</a>
            <!-- <a href="{{ route('tips.index') }}" class="flex items-center gap-3 rounded-xl px-3 py-2 {{ request()->routeIs('tips.*') ? 'bg-forest-50 text-forest-900' : 'text-gray-600 hover:bg-white hover:text-forest-800' }}">Tips</a> -->
            <a href="{{ route('messages.index') }}" class="flex items-center gap-3 rounded-xl px-3 py-2 {{ request()->routeIs('messages.*') ? 'bg-forest-50 text-forest-900' : 'text-gray-600 hover:bg-white hover:text-forest-800' }}">Chat</a>
            <a href="{{ route('leaderboard.index') }}" class="flex items-center gap-3 rounded-xl px-3 py-2 {{ request()->routeIs('leaderboard.*') ? 'bg-forest-50 text-forest-900' : 'text-gray-600 hover:bg-white hover:text-forest-800' }}">Scores</a>
        </nav>

        <div class="space-y-2">
            <a href="{{ route('posts.create') }}" class="btn-primary w-full">Create post</a>
            <a href="{{ route('alerts.create') }}" class="btn-secondary w-full">Create alert</a>
        </div>
    </div>
</aside>
