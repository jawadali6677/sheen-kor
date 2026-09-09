@php
    $listItems = $conversations->map(function ($item) use ($conversation) {
        return [
            'id' => $item->id,
            'url' => route('messages.show', $item),
            'title' => $item->displayTitle(auth()->user()),
            'preview' => $item->latestMessage?->body ?? 'No messages yet.',
            'unread' => (int) $item->unread_count,
            'time' => $item->latestMessage?->created_at?->diffForHumans() ?? '',
            'is_group' => $item->isGroup(),
            'active' => $conversation && $conversation->id === $item->id,
            'initials' => mb_strtoupper(mb_substr($item->displayTitle(auth()->user()), 0, 1)),
        ];
    })->values();
@endphp

<div class="flex h-[calc(100vh-4rem)] overflow-hidden bg-white" x-data="{ query: '' }">
    <aside class="{{ $conversation ? 'hidden md:flex' : 'flex' }} w-full flex-col border-r border-gray-100 md:w-80 lg:w-96">
        <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-4 py-4">
            <h1 class="text-lg font-semibold text-forest-900">Conversations</h1>
            <div class="flex gap-2">
                <button type="button" class="btn-secondary px-3 py-1.5 text-xs" @click="$dispatch('open-modal', 'new-message')">New</button>
                <a href="{{ route('messages.groups.create') }}" class="btn-secondary px-3 py-1.5 text-xs">Group</a>
            </div>
        </div>
        <div class="px-4 py-3">
            <label for="conversation-search" class="sr-only">Search conversations</label>
            <input id="conversation-search" type="search" x-model="query" class="sk-input" placeholder="Search messages or people">
        </div>
        <div class="flex-1 overflow-y-auto" data-conversation-list>
            @forelse($conversations as $item)
                @php
                    $title = $item->displayTitle(auth()->user());
                    $preview = $item->latestMessage?->body ?? 'No messages yet.';
                    $haystack = mb_strtolower($title.' '.$preview);
                @endphp
                <a
                    href="{{ route('messages.show', $item) }}"
                    data-conversation-id="{{ $item->id }}"
                    class="flex items-center gap-3 px-4 py-3 hover:bg-sand-50 {{ $conversation && $conversation->id === $item->id ? 'bg-forest-50' : '' }}"
                    data-search="{{ $haystack }}"
                    x-show="query === '' || ($el.dataset.search || '').includes(query.toLowerCase())"
                >
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-forest-800 text-sm font-semibold text-white">{{ mb_strtoupper(mb_substr($title, 0, 1)) }}</div>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center justify-between gap-2">
                            <p data-conversation-title class="truncate text-sm {{ $item->unread_count > 0 ? 'font-bold text-forest-900' : 'font-medium text-forest-800' }}">{{ $title }}</p>
                            <span data-conversation-time class="shrink-0 text-[11px] text-gray-400">{{ $item->latestMessage?->created_at?->diffForHumans() }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-2" data-conversation-unread-holder>
                            <p data-conversation-preview class="truncate text-sm {{ $item->unread_count > 0 ? 'font-semibold text-gray-700' : 'text-gray-500' }}">{{ $preview }}</p>
                            @if($item->unread_count > 0)
                                <span data-conversation-unread class="inline-flex min-w-5 items-center justify-center rounded-full bg-lime-400 px-1.5 text-[11px] font-bold text-forest-900">{{ $item->unread_count }}</span>
                            @endif
                        </div>
                    </div>
                </a>
            @empty
                <div class="px-6 py-12 text-center text-sm text-gray-500">
                    <p>Your conversations will appear here.</p>
                    @if($followings->isNotEmpty())
                        <button type="button" class="btn-primary mt-4" @click="$dispatch('open-modal', 'new-message')">Start a new conversation</button>
                    @endif
                </div>
            @endforelse
        </div>
        <div class="px-4 py-3 text-xs text-gray-400">{{ $conversations->links() }}</div>
    </aside>

    <section class="{{ $conversation ? 'flex' : 'hidden md:flex' }} min-w-0 flex-1 flex-col bg-sand-50">
        @if($conversation)
            <header class="flex items-center justify-between gap-3 border-b border-gray-100 bg-white px-4 py-3">
                <div class="flex min-w-0 items-center gap-3">
                    <a href="{{ route('messages.index') }}" class="rounded-full p-2 text-forest-800 hover:bg-sand-50 md:hidden" aria-label="Back">←</a>
                    @php($other = $conversation->isDirect() ? $conversation->participants->firstWhere('id', '!=', auth()->id()) : null)
                    @if($other)
                        <a href="{{ route('users.show', $other) }}"><x-user-avatar :user="$other" size="sm" /></a>
                    @endif
                    <div class="min-w-0">
                        <h2 class="truncate font-semibold text-forest-900">{{ $conversation->displayTitle(auth()->user()) }}</h2>
                        @if($conversation->isGroup())
                            <p class="truncate text-xs text-gray-500">{{ $conversation->participants->pluck('name')->join(', ') }}</p>
                        @endif
                    </div>
                </div>
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button type="button" class="rounded-full px-2 py-1 text-xl text-gray-500 hover:bg-sand-50" aria-label="More options">⋮</button>
                    </x-slot>
                    <x-slot name="content">
                        @if($other)
                            <x-dropdown-link :href="route('users.show', $other)">View profile</x-dropdown-link>
                        @endif
                        @can('leave', $conversation)
                            <form method="POST" action="{{ route('messages.participants.destroy', $conversation) }}" data-confirm="Leave this conversation?" data-confirm-message="You will no longer receive messages from this chat." data-confirm-action="Leave">
                                @csrf
                                @method('DELETE')
                                <x-dropdown-link :href="route('messages.participants.destroy', $conversation)" onclick="event.preventDefault(); this.closest('form').requestSubmit();">Leave</x-dropdown-link>
                            </form>
                        @endcan
                    </x-slot>
                </x-dropdown>
            </header>

            @if($conversation->isGroup() && auth()->user()->can('addParticipants', $conversation) && $addableUsers->isNotEmpty())
                <form method="POST" action="{{ route('messages.participants.store', $conversation) }}" class="flex gap-2 border-b border-gray-100 bg-white px-4 py-3">
                    @csrf
                    <select name="user_ids[]" multiple class="sk-input h-16 flex-1">
                        @foreach($addableUsers as $user)
                            <option value="{{ $user->id }}">{{ $user->name }}</option>
                        @endforeach
                    </select>
                    <x-primary-button>Add</x-primary-button>
                </form>
            @endif

            <div
                class="flex min-h-0 flex-1 flex-col"
                x-data="chatThread(@js([
                    'conversationId' => $conversation->id,
                    'currentUserId' => auth()->id(),
                    'messages' => $messages->map->toChatPayload()->values(),
                    'storeUrl' => route('messages.messages.store', $conversation),
                    'indexUrl' => route('messages.messages.index', $conversation),
                    'csrfToken' => csrf_token(),
                ]))"
            >
                <div class="flex-1 space-y-2 overflow-y-auto px-4 py-4" x-ref="scroller">
                    <button type="button" class="mx-auto mb-2 block text-xs font-semibold text-forest-700" x-show="hasOlder" @click="loadOlder()" x-text="loadingOlder ? 'Loading…' : 'Load earlier messages'"></button>
                    <template x-for="row in grouped" :key="row.type === 'date' ? row.id : row.message.id">
                        <div>
                            <template x-if="row.type === 'date'">
                                <p class="py-3 text-center text-xs font-semibold uppercase tracking-wide text-gray-400" x-text="row.label"></p>
                            </template>
                            <template x-if="row.type === 'message'">
                                <div class="flex" :class="row.message.user.id === currentUserId ? 'justify-end' : 'justify-start'">
                                    <div class="max-w-[80%] rounded-2xl px-3 py-2" :class="row.message.user.id === currentUserId ? 'bg-forest-800 text-white' : 'bg-white text-forest-900 shadow-soft'">
                                        <p class="text-xs opacity-70" x-show="!row.grouped && row.message.user.id !== currentUserId" x-text="row.message.user.name"></p>
                                        <p class="whitespace-pre-wrap break-words" x-text="row.message.body"></p>
                                        <p class="mt-1 text-[10px] opacity-60" x-text="timeLabel(row.message.created_at)"></p>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>
                    <p x-show="messages.length === 0" class="py-10 text-center text-gray-500">No messages yet. Say hello.</p>
                </div>

                @can('send', $conversation)
                    <form class="flex items-end gap-2 border-t border-gray-100 bg-white px-4 py-3" @submit.prevent="send">
                        <span class="pb-2 text-lg" aria-hidden="true">😊</span>
                        <label for="chat-body" class="sr-only">Write a message</label>
                        <textarea
                            id="chat-body"
                            x-model="body"
                            rows="1"
                            maxlength="2000"
                            class="sk-input max-h-32 min-h-[2.75rem] flex-1 resize-none"
                            placeholder="Write a message..."
                            @keydown="onComposerKeydown($event)"
                        ></textarea>
                        <x-primary-button x-bind:disabled="sending">Send</x-primary-button>
                    </form>
                @endcan
            </div>
        @else
            <div class="flex flex-1 flex-col items-center justify-center px-6 text-center">
                <x-application-logo class="mb-4 h-12 w-12 text-forest-700" />
                <h2 class="text-xl font-semibold text-forest-900">Start a conversation</h2>
                <p class="mt-2 max-w-sm text-sm text-gray-500">Connect with the Sheen Kor community.</p>
                <button type="button" class="btn-primary mt-6" @click="$dispatch('open-modal', 'new-message')">Find people to chat with</button>
            </div>
        @endif
    </section>
</div>

<x-modal name="new-message" maxWidth="lg">
    <div class="p-6">
        <h3 class="text-lg font-semibold text-forest-900">New message</h3>
        <p class="mt-1 text-sm text-gray-500">Choose someone you follow.</p>
        @if($followings->isEmpty())
            <p class="mt-4 text-sm text-gray-600">Follow people from their profile first, then you can message them.</p>
        @else
            <ul class="mt-4 max-h-80 space-y-2 overflow-y-auto">
                @foreach($followings as $person)
                    <li>
                        <form method="POST" action="{{ route('messages.direct.store') }}">
                            @csrf
                            <input type="hidden" name="user_id" value="{{ $person->id }}">
                            <button type="submit" class="flex w-full items-center gap-3 rounded-xl px-2 py-2 text-left hover:bg-sand-50">
                                <x-user-avatar :user="$person" size="sm" />
                                <span>
                                    <span class="block font-semibold text-forest-900">{{ $person->name }}</span>
                                    @if($person->username)
                                        <span class="text-sm text-gray-500">{{ '@'.$person->username }}</span>
                                    @endif
                                </span>
                            </button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</x-modal>
