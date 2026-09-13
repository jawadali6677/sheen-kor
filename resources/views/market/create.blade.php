<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            Create listing
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-3xl">
            <x-flash />
            <div class="sk-card p-6 md:p-8">
                <p class="mb-6 text-gray-600">Give • Exchange • Reuse. List something that can have a second life.</p>

                <form
                    action="{{ route('market.store') }}"
                    method="POST"
                    enctype="multipart/form-data"
                    class="relative"
                    x-data="{
                        listingType: @js(old('listing_type', 'sell')),
                        submitting: false,
                        onSubmit(event) {
                            if (event.defaultPrevented) {
                                return;
                            }

                            if (this.submitting) {
                                event.preventDefault();
                                return;
                            }

                            this.submitting = true;
                        },
                    }"
                    @submit="onSubmit($event)"
                >
                    @csrf

                    <div class="mb-6">
                        <label for="title" class="block text-sm font-medium text-gray-700">Title</label>
                        <input type="text" name="title" id="title" value="{{ old('title') }}" class="sk-input" required>
                    </div>

                    <div class="mb-6">
                        <label for="market_category_id" class="block text-sm font-medium text-gray-700">Category</label>
                        <select name="market_category_id" id="market_category_id" class="sk-input" required>
                            <option value="">Select category</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" @selected(old('market_category_id') == $category->id)>
                                    {{ $category->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-6">
                        <label for="listing_type" class="block text-sm font-medium text-gray-700">Listing type</label>
                        <select name="listing_type" id="listing_type" class="sk-input" x-model="listingType" required>
                            @foreach($types as $type)
                                <option value="{{ $type->value }}" @selected(old('listing_type') === $type->value)>
                                    {{ $type->label() }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-6" x-show="listingType === 'sell'" x-cloak>
                        <label for="price" class="block text-sm font-medium text-gray-700">Price</label>
                        <input type="number" name="price" id="price" value="{{ old('price') }}" min="0.01" step="0.01" class="sk-input" x-bind:disabled="listingType !== 'sell'">
                    </div>

                    <div class="mb-6" x-show="listingType === 'exchange'" x-cloak>
                        <label for="exchange_details" class="block text-sm font-medium text-gray-700">What would you like in exchange?</label>
                        <textarea name="exchange_details" id="exchange_details" rows="3" class="sk-input" x-bind:disabled="listingType !== 'exchange'">{{ old('exchange_details') }}</textarea>
                    </div>

                    <div class="mb-6">
                        <label for="condition" class="block text-sm font-medium text-gray-700">Condition</label>
                        <select name="condition" id="condition" class="sk-input" required>
                            @foreach($conditions as $condition)
                                <option value="{{ $condition->value }}" @selected(old('condition') === $condition->value)>
                                    {{ $condition->label() }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-6">
                        <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
                        <textarea name="description" id="description" rows="8" class="sk-input" required>{{ old('description') }}</textarea>
                    </div>

                    @include('partials.location-map', [
                        'mapId' => 'market-create-map',
                        'latName' => 'latitude',
                        'lngName' => 'longitude',
                        'nameField' => 'location_name',
                        'nameLabel' => 'Location on the map',
                        'requiredName' => true,
                        'lat' => old('latitude'),
                        'lng' => old('longitude'),
                        'name' => old('location_name'),
                    ])

                    <x-media-uploader
                        label="Add photos"
                        hint="The first photo becomes the cover. You can add up to 8 photos (5 MB each). Videos are not supported."
                        :require-image="true"
                        :max-images="8"
                        :max-videos="0"
                    />

                    <div class="flex items-center gap-4">
                        <button type="submit" class="btn-primary" x-bind:disabled="submitting">Submit listing</button>
                        <a href="{{ route('market.index') }}" class="btn-secondary">Cancel</a>
                    </div>

                    <div
                        x-cloak
                        x-show="submitting"
                        class="absolute inset-0 z-10 flex flex-col items-center justify-center gap-2 rounded-xl bg-white/90 px-6 text-center"
                        role="status"
                        aria-live="polite"
                        aria-busy="true"
                    >
                        <p class="font-semibold text-forest-900">Checking your listing...</p>
                        <p class="text-sm text-gray-500">Verifying content...</p>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
