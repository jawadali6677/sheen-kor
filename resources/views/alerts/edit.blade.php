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

                    <div class="mb-6">
                        <label for="location_name" class="block font-medium text-sm text-gray-700">Location</label>
                        <input type="text" name="location_name" id="location_name" value="{{ old('location_name', $alert->location_name) }}" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                        <div>
                            <label for="latitude" class="block font-medium text-sm text-gray-700">Latitude (optional)</label>
                            <input type="text" name="latitude" id="latitude" value="{{ old('latitude', $alert->latitude) }}" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                        </div>
                        <div>
                            <label for="longitude" class="block font-medium text-sm text-gray-700">Longitude (optional)</label>
                            <input type="text" name="longitude" id="longitude" value="{{ old('longitude', $alert->longitude) }}" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                        </div>
                    </div>

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

                    <div class="mb-6">
                        <label class="block font-medium text-sm text-gray-700">Current photo</label>
                        <img src="{{ asset('storage/' . $alert->featured_image) }}" alt="{{ $alert->title }}" class="mt-2 h-40 object-cover rounded">
                    </div>

                    <div class="mb-6">
                        <label for="featured_image" class="block font-medium text-sm text-gray-700">Replace photo</label>
                        <input type="file" name="featured_image" id="featured_image" accept="image/jpeg,image/png,image/webp" class="mt-1 block w-full">
                    </div>

                    <div class="mb-6">
                        <label for="images" class="block font-medium text-sm text-gray-700">Add more photos</label>
                        <input type="file" name="images[]" id="images" multiple accept="image/jpeg,image/png,image/webp" class="mt-1 block w-full">
                    </div>

                    <div class="flex items-center gap-4">
                        <button type="submit" class="px-5 py-2 bg-gray-800 text-white rounded">Save Alert</button>
                        <a href="{{ route('alerts.show', $alert) }}" class="px-5 py-2 bg-gray-200 text-gray-700 rounded">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

</x-app-layout>
