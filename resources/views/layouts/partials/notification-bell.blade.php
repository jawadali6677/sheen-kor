<div class="relative">
    <button
        type="button"
        class="relative inline-flex h-10 w-10 items-center justify-center rounded-full text-gray-500 hover:bg-sand-50 hover:text-forest-800"
        @click="$store.notifications.open = ! $store.notifications.open"
        aria-label="{{ __('Notifications') }}"
    >
        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 1 0-12 0v3.2c0 .5-.2 1-.6 1.4L4 17h5m6 0v1a3 3 0 1 1-6 0v-1m6 0H9"/>
        </svg>
        <span
            x-show="$store.notifications.unread > 0"
            x-cloak
            x-text="$store.notifications.unread"
            class="absolute -right-0.5 -top-0.5 inline-flex min-w-5 items-center justify-center rounded-full bg-lime-400 px-1 text-[10px] font-bold text-forest-900"
        >{{ $unreadNotifications }}</span>
    </button>

    <div
        x-show="$store.notifications.open"
        x-cloak
        @click.outside="$store.notifications.open = false"
        class="absolute right-0 z-50 mt-2 w-80 overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-card sm:w-96"
    >
        <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3">
            <p class="text-sm font-semibold text-forest-900">Notifications</p>
            <button
                type="button"
                class="text-xs font-medium text-forest-800 hover:underline disabled:text-gray-400"
                x-show="$store.notifications.unread > 0"
                @click="$store.notifications.markAllRead()"
            >Mark all as read</button>
        </div>
        <div class="max-h-96 overflow-y-auto">
            <template x-if="$store.notifications.items.length === 0">
                <p class="px-4 py-8 text-center text-sm text-gray-500">You're all caught up.</p>
            </template>
            <template x-for="item in $store.notifications.items" :key="item.id">
                <button
                    type="button"
                    class="flex w-full items-start gap-3 px-4 py-3 text-left hover:bg-sand-50"
                    :class="item.read_at ? 'opacity-70' : 'bg-forest-50/40'"
                    @click="$store.notifications.openItem(item)"
                >
                    <div class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center overflow-hidden rounded-full bg-forest-800 text-xs font-semibold text-white">
                        <img x-show="item.actor_avatar_url" :src="item.actor_avatar_url" :alt="item.actor_name" class="h-8 w-8 object-cover">
                        <span x-show="! item.actor_avatar_url" x-text="(item.actor_name || 'N').charAt(0).toUpperCase()"></span>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-forest-900" x-text="item.title"></p>
                        <p class="truncate text-sm text-gray-600" x-text="item.body"></p>
                        <p class="mt-0.5 text-[11px] text-gray-400" x-text="item.created_at"></p>
                    </div>
                </button>
            </template>
        </div>
    </div>
</div>
