<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Edit {{ $package->name }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
            <div class="rounded-lg bg-white p-6 shadow-sm">
                <p class="text-sm text-gray-600">{{ $package->type->label() }} · {{ $package->slug }}</p>
                <p class="mt-1 text-xs text-gray-500">Package type is fixed and is not accepted from this form.</p>

                <form method="POST" action="{{ route('admin.monetization.packages.update', $package) }}" class="mt-6 space-y-6">
                    @csrf
                    @method('PATCH')

                    <div>
                        <label for="price" class="block text-sm font-medium text-gray-700">Price</label>
                        <input id="price" type="number" name="price" value="{{ old('price', $package->price) }}" min="0" step="0.01" class="mt-1 block w-full rounded-md border-gray-300" required>
                        <x-input-error class="mt-2" :messages="$errors->get('price')" />
                    </div>

                    <div>
                        <label for="currency" class="block text-sm font-medium text-gray-700">Currency</label>
                        <input id="currency" type="text" name="currency" value="{{ old('currency', $package->currency) }}" maxlength="3" class="mt-1 block w-full rounded-md border-gray-300 uppercase" required>
                        <x-input-error class="mt-2" :messages="$errors->get('currency')" />
                    </div>

                    <div>
                        <label for="duration_days" class="block text-sm font-medium text-gray-700">Duration (days)</label>
                        <input id="duration_days" type="number" name="duration_days" value="{{ old('duration_days', $package->duration_days) }}" min="1" max="3650" class="mt-1 block w-full rounded-md border-gray-300" required>
                        <x-input-error class="mt-2" :messages="$errors->get('duration_days')" />
                    </div>

                    <div>
                        <label for="is_enabled" class="block text-sm font-medium text-gray-700">Status</label>
                        <select id="is_enabled" name="is_enabled" class="mt-1 block w-full rounded-md border-gray-300">
                            @php
                                $enabledValue = (string) old('is_enabled', $package->is_enabled ? '1' : '0');
                            @endphp
                            <option value="0" @selected($enabledValue === '0')>Disabled</option>
                            <option value="1" @selected($enabledValue === '1')>Enabled</option>
                        </select>
                        <x-input-error class="mt-2" :messages="$errors->get('is_enabled')" />
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <button type="submit" class="rounded bg-gray-800 px-4 py-2 text-white">Save package</button>
                        <a href="{{ route('admin.monetization.index') }}" class="rounded px-4 py-2 text-gray-700 ring-1 ring-gray-200">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
