<x-app-layout>

    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Edit Alert
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">

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

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <form action="{{ route('alerts.update', $alert) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')

                    <div class="mb-6">
                        <label for="title" class="block font-medium text-sm text-gray-700">Title</label>
                        <input type="text" name="title" id="title" value="{{ old('title', $alert->title) }}" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required>
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
                        <select name="severity" id="severity" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required>
                            <option value="low" @selected(old('severity', $alert->severity) === 'low')>Low</option>
                            <option value="medium" @selected(old('severity', $alert->severity) === 'medium')>Medium</option>
                            <option value="high" @selected(old('severity', $alert->severity) === 'high')>High</option>
                        </select>
                    </div>

                    <div class="mb-6">
                        <label for="description" class="block font-medium text-sm text-gray-700">What did you see?</label>
                        <textarea name="description" id="description" rows="8" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required>{{ old('description', $alert->description) }}</textarea>
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
                        <button type="submit" class="px-5 py-2 bg-gray-800 text-white rounded">Save Alert</button>
                        <a href="{{ route('alerts.show', $alert) }}" class="px-5 py-2 bg-gray-200 text-gray-700 rounded">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

</x-app-layout>
