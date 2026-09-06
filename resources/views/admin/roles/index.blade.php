<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Roles</h2>
            <a href="{{ route('admin.roles.create') }}" class="px-4 py-2 bg-gray-800 text-white rounded text-sm">New role</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="mb-6 p-4 bg-green-100 text-green-700 rounded">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="mb-6 p-4 bg-red-100 text-red-700 rounded">{{ session('error') }}</div>
            @endif

            <p class="text-sm text-gray-600 mb-4">
                Create roles such as Hiker or Tree planter, then choose which permissions that role has. You can only grant permissions you already have.
            </p>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-left">
                        <tr>
                            <th class="px-4 py-3">Role</th>
                            <th class="px-4 py-3">Permissions</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($roles as $role)
                            <tr class="border-t">
                                <td class="px-4 py-3">
                                    <p class="font-medium">{{ $role->name }}</p>
                                    <p class="text-gray-500">{{ $role->slug }}@if($role->is_system) · system @endif</p>
                                    @if($role->description)
                                        <p class="text-gray-500 mt-1">{{ $role->description }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    {{ $role->isAdmin() ? 'All permissions' : $role->permission_records_count }}
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <a href="{{ route('admin.roles.edit', $role) }}" class="text-blue-700">Edit</a>
                                    @unless($role->is_system)
                                        <form action="{{ route('admin.roles.destroy', $role) }}" method="POST" class="inline ms-3" onsubmit="return confirm('Delete this role?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600">Delete</button>
                                        </form>
                                    @endunless
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
