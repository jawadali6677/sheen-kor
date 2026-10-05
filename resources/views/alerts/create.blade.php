<x-app-layout>
    <div class="mx-auto max-w-3xl space-y-4">
        <h1 class="text-xl font-semibold text-forest-900">Report an Alert</h1>

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
                <p class="text-gray-600 mb-6">
                    See dumping, pollution, smoke, or other harm to nature? Take a photo and report the place so the community can see it.
                </p>

                <form action="{{ route('alerts.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf

                    <div class="mb-6">
                        <label for="title" class="block font-medium text-sm text-gray-700">Title</label>
                        <input
                            type="text"
                            name="title"
                            id="title"
                            value="{{ old('title') }}"
                            class="sk-input"
                            placeholder="What is happening?"
                            required
                        >
                    </div>

                    @include('partials.location-map', [
                        'mapId' => 'alert-create-map',
                        'latName' => 'latitude',
                        'lngName' => 'longitude',
                        'nameField' => 'location_name',
                        'nameLabel' => 'Location on the map',
                        'requiredName' => true,
                        'lat' => old('latitude'),
                        'lng' => old('longitude'),
                        'name' => old('location_name'),
                    ])

                    <div class="mb-6">
                        <label for="severity" class="block font-medium text-sm text-gray-700">Severity</label>
                        <select name="severity" id="severity" class="sk-input" required>
                            <option value="low" @selected(old('severity') === 'low')>Low</option>
                            <option value="medium" @selected(old('severity', 'medium') === 'medium')>Medium</option>
                            <option value="high" @selected(old('severity') === 'high')>High</option>
                        </select>
                    </div>

                    <div class="mb-6">
                        <label for="description" class="block font-medium text-sm text-gray-700">What did you see?</label>
                        <textarea
                            name="description"
                            id="description"
                            rows="8"
                            class="sk-input"
                            placeholder="Describe the problem and anything people should know..."
                            required
                        >{{ old('description') }}</textarea>
                    </div>

                    <x-media-uploader
                        label="Add photos & videos"
                        hint="Add at least one photo as evidence. Extra photos and short videos are optional."
                        :require-image="true"
                    />

                    <div class="flex items-center gap-4">
                        <button type="submit" class="btn-primary">
                            Post Alert
                        </button>
                        <a href="{{ route('alerts.index') }}" class="btn-secondary">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
    </div>
</x-app-layout>
