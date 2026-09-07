<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ $conversation->displayTitle(auth()->user()) }}
                </h2>
                @if($conversation->isGroup())
                    <p class="text-sm text-gray-500">{{ $conversation->participants->pluck('name')->join(', ') }}</p>
                @endif
            </div>
            <a href="{{ route('messages.index') }}" class="text-sm text-gray-600">{{ __('Back') }}</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if(session('success'))
                <div class="p-4 bg-green-100 text-green-700 rounded">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="p-4 bg-red-100 text-red-700 rounded">
                    {{ session('error') }}
                </div>
            @endif

            @if($conversation->isGroup() && auth()->user()->can('addParticipants', $conversation) && $addableUsers->isNotEmpty())
                <div class="bg-white shadow-sm sm:rounded-lg p-4">
                    <form method="POST" action="{{ route('messages.participants.store', $conversation) }}" class="flex flex-col sm:flex-row sm:items-end gap-3">
                        @csrf
                        <div class="flex-1">
                            <x-input-label for="add_user_ids" :value="__('Add people you follow')" />
                            <select id="add_user_ids" name="user_ids[]" multiple size="4" class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                @foreach($addableUsers as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                                @endforeach
                            </select>
                            <x-input-error class="mt-2" :messages="$errors->get('user_ids')" />
                        </div>
                        <x-primary-button>{{ __('Add') }}</x-primary-button>
                    </form>
                </div>
            @endif

            <div
                class="bg-white overflow-hidden shadow-sm sm:rounded-lg"
                x-data="chatThread(@js([
                    'conversationId' => $conversation->id,
                    'currentUserId' => auth()->id(),
                    'messages' => $messages->map->toChatPayload()->values(),
                    'storeUrl' => route('messages.messages.store', $conversation),
                    'indexUrl' => route('messages.messages.index', $conversation),
                    'csrfToken' => csrf_token(),
                ]))"
            >
                <div class="h-[28rem] overflow-y-auto p-4 flex flex-col gap-3" x-ref="scroller">
                    <template x-for="message in messages" :key="message.id">
                        <div class="flex" :class="message.user.id === currentUserId ? 'justify-end' : 'justify-start'">
                            <div class="max-w-[80%] rounded-lg px-3 py-2" :class="message.user.id === currentUserId ? 'bg-gray-800 text-white' : 'bg-gray-100 text-gray-900'">
                                <p class="text-xs opacity-75" x-text="message.user.name"></p>
                                <p class="whitespace-pre-wrap break-words" x-text="message.body"></p>
                            </div>
                        </div>
                    </template>
                    <p x-show="messages.length === 0" class="text-center text-gray-500 py-10">{{ __('No messages yet. Say hello.') }}</p>
                </div>

                @can('send', $conversation)
                    <form class="border-t border-gray-200 p-4 flex gap-3" @submit.prevent="send">
                        <x-text-input x-model="body" class="block w-full" type="text" maxlength="2000" placeholder="Write a message" required />
                        <x-primary-button x-bind:disabled="sending">{{ __('Send') }}</x-primary-button>
                    </form>
                @endcan
            </div>

            @if($conversation->isGroup())
                <div class="bg-white shadow-sm sm:rounded-lg p-4 space-y-3">
                    <h3 class="font-semibold text-gray-900">{{ __('Members') }}</h3>
                    <ul class="divide-y divide-gray-200">
                        @foreach($conversation->participants as $participant)
                            <li class="flex items-center justify-between gap-3 py-2">
                                <span class="text-sm text-gray-800">
                                    {{ $participant->name }}
                                    @if($conversation->isAdmin($participant))
                                        <span class="text-xs text-gray-500">({{ __('admin') }})</span>
                                    @endif
                                </span>
                                @if(auth()->user()->can('removeParticipant', $conversation) && $participant->id !== auth()->id())
                                    <form method="POST" action="{{ route('messages.participants.remove', [$conversation, $participant]) }}" onsubmit="return confirm('Remove this person from the group?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-sm text-red-600">{{ __('Remove') }}</button>
                                    </form>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="flex flex-wrap items-center gap-4">
                @can('leave', $conversation)
                    <form method="POST" action="{{ route('messages.participants.destroy', $conversation) }}" onsubmit="return confirm('Leave this conversation?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-sm text-red-600">{{ __('Leave conversation') }}</button>
                    </form>
                @endcan

                @can('delete', $conversation)
                    <form method="POST" action="{{ route('messages.destroy', $conversation) }}" onsubmit="return confirm('Delete this group for everyone?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-sm text-red-600">{{ __('Delete group') }}</button>
                    </form>
                @endcan
            </div>
        </div>
    </div>
</x-app-layout>
