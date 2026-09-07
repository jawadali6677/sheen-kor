<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Messages') }}
            </h2>
            <a href="{{ route('messages.groups.create') }}" class="px-4 py-2 bg-gray-800 text-white rounded text-sm">
                {{ __('New group') }}
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="mb-6 p-4 bg-green-100 text-green-700 rounded">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="mb-6 p-4 bg-red-100 text-red-700 rounded">
                    {{ session('error') }}
                </div>
            @endif

            <div
                class="bg-white overflow-hidden shadow-sm sm:rounded-lg"
                x-data
                x-init="
                    if (window.Echo) {
                        window.Echo.private('users.{{ auth()->id() }}.conversations')
                            .listen('.MessageSent', () => window.location.reload());
                    }
                "
            >
                <div class="divide-y divide-gray-200">
                    @forelse($conversations as $conversation)
                        <a href="{{ route('messages.show', $conversation) }}" class="flex items-start justify-between gap-4 p-4 hover:bg-gray-50">
                            <div class="min-w-0">
                                <p class="font-semibold text-gray-900 truncate">{{ $conversation->displayTitle(auth()->user()) }}</p>
                                @if($conversation->isGroup())
                                    <p class="text-xs text-gray-500">{{ $conversation->participants->count() }} members</p>
                                @endif
                                <p class="text-sm text-gray-600 truncate">
                                    {{ $conversation->latestMessage?->body ?? __('No messages yet.') }}
                                </p>
                            </div>
                            @if($conversation->unread_count > 0)
                                <span class="inline-flex items-center justify-center min-w-6 h-6 px-2 rounded-full bg-gray-800 text-white text-xs">
                                    {{ $conversation->unread_count }}
                                </span>
                            @endif
                        </a>
                    @empty
                        <p class="p-6 text-center text-gray-500">{{ __('No conversations yet. Message someone from their profile.') }}</p>
                    @endforelse
                </div>
            </div>

            <div class="mt-4">
                {{ $conversations->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
