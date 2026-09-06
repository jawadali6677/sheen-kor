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

            <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-left">
                        <tr>
                            <th class="px-4 py-3">Name</th>
                            <th class="px-4 py-3">Email</th>
                            <th class="px-4 py-3">Points</th>
                            <th class="px-4 py-3">Role</th>
                            <th class="px-4 py-3">Active</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($users as $member)
                            <tr class="border-t">
                                <td class="px-4 py-3 font-medium">{{ $member->name }}</td>
                                <td class="px-4 py-3">{{ $member->email }}</td>
                                <td class="px-4 py-3">{{ number_format($member->score) }}</td>
                                <td class="px-4 py-3" colspan="3">
                                    <form method="POST" action="{{ route('admin.users.update', $member) }}" class="flex flex-wrap items-center gap-2">
                                        @csrf
                                        @method('PATCH')
                                        <select name="role" class="border-gray-300 rounded-md text-sm">
                                            @foreach($roles as $role)
                                                <option value="{{ $role->value }}" @selected($member->role === $role)>{{ $role->label() }}</option>
                                            @endforeach
                                        </select>
                                        <select name="status" class="border-gray-300 rounded-md text-sm">
                                            <option value="1" @selected($member->status)>Active</option>
                                            <option value="0" @selected(! $member->status)>Disabled</option>
                                        </select>
                                        <button type="submit" class="px-3 py-1 bg-gray-800 text-white rounded text-sm">Save</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-6">
                {{ $users->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
