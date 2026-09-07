@auth
    <nav class="fixed inset-x-0 bottom-0 z-40 border-t border-gray-100 bg-white px-2 py-1 pb-[max(0.35rem,env(safe-area-inset-bottom))] lg:hidden" aria-label="{{ __('Mobile') }}">
        <div class="grid grid-cols-5 items-center text-[11px] font-medium text-gray-500">
            <a href="{{ route('posts.index') }}" class="flex flex-col items-center gap-1 py-2 {{ request()->routeIs('posts.index') ? 'text-forest-800' : '' }}">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1v-9.5Z"/></svg>
                Home
            </a>
            <a href="{{ route('explore.index') }}" class="flex flex-col items-center gap-1 py-2 {{ request()->routeIs('explore.*') ? 'text-forest-800' : '' }}">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M11 19a8 8 0 1 1 0-16 8 8 0 0 1 0 16Z"/></svg>
                Explore
            </a>
            <div class="relative flex justify-center" x-data="{ open: false }">
                <button type="button" class="-mt-5 flex h-14 w-14 items-center justify-center rounded-full bg-lime-400 text-2xl font-semibold text-forest-900 shadow-card" @click="open = ! open" aria-label="{{ __('Create') }}">+</button>
                <div x-show="open" x-cloak @click.outside="open = false" class="absolute bottom-16 w-44 rounded-2xl border border-gray-100 bg-white p-2 shadow-card">
                    <a href="{{ route('posts.create') }}" class="block rounded-xl px-3 py-2 text-sm text-forest-900 hover:bg-sand-50">Create post</a>
                    <a href="{{ route('alerts.create') }}" class="block rounded-xl px-3 py-2 text-sm text-forest-900 hover:bg-sand-50">Create alert</a>
                </div>
            </div>
            <a href="{{ route('alerts.index') }}" class="flex flex-col items-center gap-1 py-2 {{ request()->routeIs('alerts.*') ? 'text-forest-800' : '' }}">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.3 4.2 2.6 18a2 2 0 0 0 1.7 3h15.4a2 2 0 0 0 1.7-3L13.7 4.2a2 2 0 0 0-3.4 0Z"/></svg>
                Alerts
            </a>
            <a href="{{ route('users.show', auth()->user()) }}" class="flex flex-col items-center gap-1 py-2 {{ request()->routeIs('users.show', 'profile.*') ? 'text-forest-800' : '' }}">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0ZM4 20a8 8 0 0 1 16 0"/></svg>
                Profile
            </a>
        </div>
    </nav>
@endauth
