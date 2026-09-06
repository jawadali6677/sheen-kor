<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Manage users
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="mb-6 p-4 bg-green-100 text-green-700 rounded">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="mb-6 p-4 bg-red-100 text-red-700 rounded">{{ session('error') }}</div>
            @endif

            <p class="text-sm text-gray-600 mb-4">
                Assign a role, then optionally grant extra permissions on top of that role.
            </p>

            <div class="space-y-4">
                @foreach($users as $member)
                    <form method="POST" action="{{ route('admin.users.update', $member) }}" class="bg-white shadow-sm sm:rounded-lg p-4">
                        @csrf
                        @method('PATCH')
                        <div class="flex flex-wrap items-center gap-3">
                            <div class="min-w-[12rem]">
                                <p class="font-medium">{{ $member->name }}</p>
                                <p class="text-sm text-gray-500">{{ $member->email }} · {{ number_format($member->score) }} pts</p>
                            </div>
                            <select name="role" class="border-gray-300 rounded-md text-sm">
                                @foreach($roles as $role)
                                    <option value="{{ $role->slug }}" @selected($member->role === $role->slug)>{{ $role->name }}</option>
                                @endforeach
                            </select>
                            <select name="status" class="border-gray-300 rounded-md text-sm">
                                <option value="1" @selected($member->status)>Active</option>
                                <option value="0" @selected(! $member->status)>Disabled</option>
                            </select>
                            <button type="submit" class="px-3 py-1 bg-gray-800 text-white rounded text-sm">Save</button>
                        </div>
                        <details class="mt-3">
                            <summary class="text-sm text-gray-700 cursor-pointer">Extra permissions</summary>
                            <div class="mt-2 grid sm:grid-cols-2 gap-2">
                                @foreach($permissions as $permission)
                                    <label class="flex items-start gap-2 text-sm">
                                        <input
                                            type="checkbox"
                                            name="permissions[]"
                                            value="{{ $permission->value }}"
                                            class="rounded border-gray-300 mt-0.5"
                                            @checked($member->extraPermissionRecords->contains('permission', $permission->value))
                                        >
                                        <span>{{ $permission->label() }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </details>
                    </form>
                @endforeach
            </div>

            <div class="mt-6">
                {{ $users->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
