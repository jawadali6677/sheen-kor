<x-app-layout>
    <div class="mx-auto max-w-3xl space-y-4">
        <h1 class="text-xl font-semibold text-forest-900">Edit Alert</h1>

            @if ($errors->any())
                <div class="mb-6 p-4 bg-red-100 text-red-700 rounded">
                    <ul class="list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if(session('error'))
                <div class="mb-6 p-4 bg-red-100 text-red-700 rounded">
                    {{ session('error') }}
                </div>
            @endif

            <div class="sk-card p-6">
                <form action="{{ route('alerts.update', $alert) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')

                    <div class="mb-6">
                        <label for="title" class="block font-medium text-sm text-gray-700">Title</label>
                        <input type="text" name="title" id="title" value="{{ old('title', $alert->title) }}" class="sk-input" required>
                    </div>

                    @include('partials.location-map', [
                        'mapId' => 'alert-edit-map',
                        'latName' => 'latitude',
                        'lngName' => 'longitude',
                        'nameField' => 'location_name',
                        'nameLabel' => 'Location on the map',
                        'requiredName' => true,
                        'lat' => old('latitude', $alert->latitude),
                        'lng' => old('longitude', $alert->longitude),
                        'name' => old('location_name', $alert->location_name),
                    ])

                    <div class="mb-6">
                        <label for="severity" class="block font-medium text-sm text-gray-700">Severity</label>
                        <select name="severity" id="severity" class="sk-input" required>
                            <option value="low" @selected(old('severity', $alert->severity) === 'low')>Low</option>
                            <option value="medium" @selected(old('severity', $alert->severity) === 'medium')>Medium</option>
                            <option value="high" @selected(old('severity', $alert->severity) === 'high')>High</option>
                        </select>
                    </div>

                    <div class="mb-6">
                        <label for="description" class="block font-medium text-sm text-gray-700">What did you see?</label>
                        <textarea name="description" id="description" rows="8" class="sk-input" required>{{ old('description', $alert->description) }}</textarea>
                    </div>

                    @if($alert->hasMedia())
                        <div class="mb-6">
                            <p class="mb-3 text-sm font-medium text-gray-700">Current media</p>
                            <div class="flex flex-wrap gap-3">
                                @if($alert->featured_image)
                                    <img src="{{ asset('storage/' . $alert->featured_image) }}" alt="{{ $alert->title }}" class="h-32 w-40 rounded-xl object-cover js-lightbox">
                                @endif
                                @foreach($alert->reportImages as $image)
                                    <div class="h-32 w-40 overflow-hidden rounded-xl">
                                        <x-media-item :media="$image" alt="Alert media" class="h-32 w-40 object-cover" />
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <x-media-uploader
                        label="Add photos & videos"
                        hint="New photos and videos are added as evidence. The first new photo replaces the cover."
                    />

                    <div class="flex items-center gap-4">
                        <button type="submit" class="btn-primary">Save Alert</button>
                        <a href="{{ route('alerts.show', $alert) }}" class="btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
    </div>
</x-app-layout>
