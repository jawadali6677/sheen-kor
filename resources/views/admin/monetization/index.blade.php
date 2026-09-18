<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Monetization</h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-6xl space-y-8 sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="rounded bg-green-100 p-4 text-green-700">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="rounded bg-red-100 p-4 text-red-700">{{ session('error') }}</div>
            @endif

            <p class="mb-4 flex flex-wrap gap-4">
                <a href="{{ route('admin.monetization.dashboard') }}" class="text-sm font-semibold text-blue-700">Dashboard</a>
                <a href="{{ route('admin.monetization.green-ticks.index') }}" class="text-sm font-semibold text-blue-700">Review Green Tick requests</a>
                <a href="{{ route('admin.monetization.boosts.index') }}" class="text-sm font-semibold text-blue-700">Manage post boosts</a>
                <a href="{{ route('admin.monetization.promotions.index') }}" class="text-sm font-semibold text-blue-700">Manage listing promotions</a>
                <a href="{{ route('admin.monetization.orders.index') }}" class="text-sm font-semibold text-blue-700">Orders</a>
            </p>

            <section class="rounded-lg bg-white p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-gray-800">Settings</h3>
                <p class="mt-1 text-sm text-gray-600">Feed ads, sidebar ads, video interstitials, and rewarded settings apply immediately. Payment and checkout are still later phases.</p>

                <form method="POST" action="{{ route('admin.monetization.settings.update') }}" class="mt-6 space-y-5">
                    @csrf
                    @method('PATCH')

                    @foreach($settingDefinitions as $key => $definition)
                        @php
                            $current = old($key, $settings[$key]->value ?? '');
                        @endphp
                        <div>
                            <label for="setting-{{ $key }}" class="block text-sm font-medium text-gray-700">{{ $definition['label'] }}</label>
                            @if($definition['type'] === 'boolean')
                                <select id="setting-{{ $key }}" name="{{ $key }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm sm:w-64">
                                    <option value="1" @selected((string) $current === '1' || $current === true || $current === 1)>Enabled</option>
                                    <option value="0" @selected((string) $current === '0' || $current === false || $current === 0)>Disabled</option>
                                </select>
                            @elseif($definition['type'] === 'integer')
                                <input
                                    id="setting-{{ $key }}"
                                    type="number"
                                    name="{{ $key }}"
                                    value="{{ $current }}"
                                    min="{{ $definition['min'] ?? 0 }}"
                                    max="{{ $definition['max'] ?? 100000000 }}"
                                    class="mt-1 block w-full rounded-md border-gray-300 text-sm sm:w-64"
                                    required
                                >
                            @else
                                <input
                                    id="setting-{{ $key }}"
                                    type="text"
                                    name="{{ $key }}"
                                    value="{{ $current }}"
                                    maxlength="{{ $key === 'currency' ? 3 : 64 }}"
                                    class="mt-1 block w-full rounded-md border-gray-300 text-sm sm:w-64 {{ $key === 'currency' ? 'uppercase' : '' }}"
                                    required
                                >
                            @endif
                            <p class="mt-1 text-xs text-gray-500">{{ $definition['help'] }}</p>
                            <x-input-error class="mt-2" :messages="$errors->get($key)" />
                        </div>
                    @endforeach

                    <button type="submit" class="rounded bg-gray-800 px-4 py-2 text-sm text-white">Save settings</button>
                </form>
            </section>

            <section class="overflow-hidden rounded-lg bg-white shadow-sm">
                <div class="border-b border-gray-100 px-6 py-4">
                    <h3 class="text-lg font-semibold text-gray-800">Advertisements</h3>
                    <p class="mt-1 text-sm text-gray-600">First-party inventory for Feed, sidebar, video interstitials, and rewarded placeholders. Destination URLs cannot be changed from the browser.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-left">
                            <tr>
                                <th class="px-4 py-3">Advertisement</th>
                                <th class="px-4 py-3">Placements</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($advertisements as $advertisement)
                                <tr class="border-t">
                                    <td class="px-4 py-3">
                                        <p class="font-medium">{{ $advertisement->title }}</p>
                                        <p class="text-gray-500">{{ $advertisement->advertiser }} · {{ $advertisement->slug }}</p>
                                    </td>
                                    <td class="px-4 py-3">
                                        {{ collect([
                                            $advertisement->is_feed ? 'Feed' : null,
                                            $advertisement->is_sidebar ? 'Sidebar' : null,
                                            $advertisement->is_video ? 'Video' : null,
                                            $advertisement->is_rewarded ? 'Rewarded' : null,
                                        ])->filter()->join(' · ') }}
                                    </td>
                                    <td class="px-4 py-3">{{ $advertisement->is_enabled ? 'Enabled' : 'Disabled' }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <form method="POST" action="{{ route('admin.monetization.advertisements.update', $advertisement) }}" class="flex flex-wrap items-center justify-end gap-3">
                                            @csrf
                                            @method('PATCH')
                                            <label class="inline-flex items-center gap-1 text-xs text-gray-600">
                                                <input type="hidden" name="is_enabled" value="0">
                                                <input type="checkbox" name="is_enabled" value="1" @checked($advertisement->is_enabled)>
                                                Enabled
                                            </label>
                                            <label class="inline-flex items-center gap-1 text-xs text-gray-600">
                                                <input type="hidden" name="is_feed" value="0">
                                                <input type="checkbox" name="is_feed" value="1" @checked($advertisement->is_feed)>
                                                Feed
                                            </label>
                                            <label class="inline-flex items-center gap-1 text-xs text-gray-600">
                                                <input type="hidden" name="is_sidebar" value="0">
                                                <input type="checkbox" name="is_sidebar" value="1" @checked($advertisement->is_sidebar)>
                                                Sidebar
                                            </label>
                                            <label class="inline-flex items-center gap-1 text-xs text-gray-600">
                                                <input type="hidden" name="is_video" value="0">
                                                <input type="checkbox" name="is_video" value="1" @checked($advertisement->is_video)>
                                                Video
                                            </label>
                                            <label class="inline-flex items-center gap-1 text-xs text-gray-600">
                                                <input type="hidden" name="is_rewarded" value="0">
                                                <input type="checkbox" name="is_rewarded" value="1" @checked($advertisement->is_rewarded)>
                                                Rewarded
                                            </label>
                                            <button type="submit" class="text-blue-700">Save</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-6 text-gray-500">No advertisements yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="overflow-hidden rounded-lg bg-white shadow-sm">
                <div class="border-b border-gray-100 px-6 py-4">
                    <h3 class="text-lg font-semibold text-gray-800">Packages</h3>
                    <p class="mt-1 text-sm text-gray-600">Prices are saved on the server. Package type cannot be changed.</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-left">
                            <tr>
                                <th class="px-4 py-3">Package</th>
                                <th class="px-4 py-3">Price</th>
                                <th class="px-4 py-3">Duration</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($packages as $package)
                                <tr class="border-t">
                                    <td class="px-4 py-3">
                                        <p class="font-medium">{{ $package->name }}</p>
                                        <p class="text-gray-500">{{ $package->type->label() }} · {{ $package->slug }}</p>
                                    </td>
                                    <td class="px-4 py-3">{{ $package->price }} {{ $package->currency }}</td>
                                    <td class="px-4 py-3">{{ $package->duration_days }} days</td>
                                    <td class="px-4 py-3">{{ $package->is_enabled ? 'Enabled' : 'Disabled' }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <a href="{{ route('admin.monetization.packages.edit', $package) }}" class="text-blue-700">Edit</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-6 text-gray-500">No packages yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
