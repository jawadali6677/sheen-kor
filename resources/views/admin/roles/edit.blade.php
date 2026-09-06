<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Edit {{ $role->name }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <form method="POST" action="{{ route('admin.roles.update', $role) }}" class="space-y-6">
                    @csrf
                    @method('PUT')
                    @include('admin.roles.form', [
                        'role' => $role,
                        'permissions' => $permissions,
                        'selected' => old('permissions', $selected),
                    ])
                    <button type="submit" class="px-4 py-2 bg-gray-800 text-white rounded">Save role</button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
