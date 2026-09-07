<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('New group') }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <form method="POST" action="{{ route('messages.groups.store') }}" class="space-y-4">
                    @csrf

                    <div>
                        <x-input-label for="title" :value="__('Group name')" />
                        <x-text-input id="title" name="title" class="block mt-1 w-full" type="text" :value="old('title')" required maxlength="80" />
                        <x-input-error class="mt-2" :messages="$errors->get('title')" />
                    </div>

                    <div>
                        <x-input-label for="user_ids" :value="__('Members')" />
                        <p class="text-sm text-gray-500 mb-2">{{ __('Select at least two people you follow.') }}</p>
                        @if($users->isEmpty())
                            <p class="text-sm text-gray-600">{{ __('Follow people from their profile first, then you can add them to a group.') }}</p>
                        @else
                            <select id="user_ids" name="user_ids[]" multiple size="10" class="block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}" @selected(collect(old('user_ids', []))->contains($user->id))>
                                        {{ $user->name }}@if($user->username) ({{ '@'.$user->username }})@endif
                                    </option>
                                @endforeach
                            </select>
                        @endif
                        <x-input-error class="mt-2" :messages="$errors->get('user_ids')" />
                    </div>

                    <div class="flex items-center gap-3">
                        <x-primary-button>{{ __('Create group') }}</x-primary-button>
                        <a href="{{ route('messages.index') }}" class="text-sm text-gray-600">{{ __('Cancel') }}</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
