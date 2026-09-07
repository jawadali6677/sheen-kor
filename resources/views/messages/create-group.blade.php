<x-app-layout>
    <div class="mx-auto max-w-xl">
        <div class="sk-card p-6">
            <h1 class="text-xl font-semibold text-forest-900">{{ __('New group') }}</h1>
            <form method="POST" action="{{ route('messages.groups.store') }}" class="mt-6 space-y-4">
                @csrf
                <div>
                    <x-input-label for="title" :value="__('Group name')" />
                    <x-text-input id="title" name="title" class="block w-full" type="text" :value="old('title')" required maxlength="80" />
                    <x-input-error class="mt-2" :messages="$errors->get('title')" />
                </div>
                <div>
                    <x-input-label for="user_ids" :value="__('Members')" />
                    <p class="mb-2 text-sm text-gray-500">{{ __('Select at least two people you follow.') }}</p>
                    @if($users->isEmpty())
                        <p class="text-sm text-gray-600">{{ __('Follow people from their profile first, then you can add them to a group.') }}</p>
                    @else
                        <select id="user_ids" name="user_ids[]" multiple size="10" class="sk-input">
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
</x-app-layout>
