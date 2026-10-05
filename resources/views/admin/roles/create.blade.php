<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-forest-900">New role</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="sk-card p-6">
                <form method="POST" action="{{ route('admin.roles.store') }}" class="space-y-6">
                    @csrf
                    @include('admin.roles.form', [
                        'role' => null,
                        'permissions' => $permissions,
                        'selected' => old('permissions', $selected),
                    ])
                    <button type="submit" class="btn-primary">Create role</button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
