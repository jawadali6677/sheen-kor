<div>
    <label for="name" class="block font-medium text-sm text-gray-700">Name</label>
    <input id="name" name="name" type="text" value="{{ old('name', $role?->name) }}" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required>
    <x-input-error class="mt-2" :messages="$errors->get('name')" />
</div>

<div>
    <label for="slug" class="block font-medium text-sm text-gray-700">Slug</label>
    <input
        id="slug"
        name="slug"
        type="text"
        value="{{ old('slug', $role?->slug) }}"
        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
        @if($role?->is_system) readonly @endif
        placeholder="tree-planter"
    >
    <p class="text-xs text-gray-500 mt-1">Used internally. Example: hiker, tree-planter.</p>
    <x-input-error class="mt-2" :messages="$errors->get('slug')" />
</div>

<div>
    <label for="description" class="block font-medium text-sm text-gray-700">Description</label>
    <input id="description" name="description" type="text" value="{{ old('description', $role?->description) }}" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
    <x-input-error class="mt-2" :messages="$errors->get('description')" />
</div>

<div>
    <p class="font-medium text-sm text-gray-700 mb-2">Permissions</p>
    @if($role?->isAdmin())
        <p class="text-sm text-gray-600">The admin role always has every permission.</p>
    @else
        <div class="grid sm:grid-cols-2 gap-2">
            @foreach($permissions as $permission)
                <label class="flex items-start gap-2 text-sm">
                    <input
                        type="checkbox"
                        name="permissions[]"
                        value="{{ $permission->value }}"
                        class="rounded border-gray-300 mt-0.5"
                        @checked(in_array($permission->value, $selected, true))
                    >
                    <span>{{ $permission->label() }}</span>
                </label>
            @endforeach
        </div>
    @endif
    <x-input-error class="mt-2" :messages="$errors->get('permissions')" />
</div>
