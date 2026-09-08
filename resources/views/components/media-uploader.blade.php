@props([
    'label' => 'Add photos & videos',
    'hint' => 'Photos up to 5 MB each. Videos up to 20 MB (MP4, WebM, or MOV).',
    'featuredName' => 'featured_image',
    'imagesName' => 'images',
    'videosName' => 'videos',
    'requireImage' => false,
    'mapFirstImageToFeatured' => true,
    'maxImages' => 11,
    'maxVideos' => 3,
])

@php
    $mapFirst = $mapFirstImageToFeatured && filled($featuredName);
@endphp

<div
    class="js-media-uploader mb-6"
    x-data="mediaUploader({
        maxImages: {{ (int) $maxImages }},
        maxVideos: {{ (int) $maxVideos }},
        imageMaxBytes: 5 * 1024 * 1024,
        videoMaxBytes: 20 * 1024 * 1024,
        requireImage: {{ $requireImage ? 'true' : 'false' }},
        mapFirstImageToFeatured: {{ $mapFirst ? 'true' : 'false' }},
    })"
>
    <p class="block text-sm font-medium text-gray-700">{{ $label }}</p>
    <p class="mt-1 text-sm text-gray-500">{{ $hint }}</p>

    <button
        type="button"
        class="mt-3 flex w-full flex-col items-center justify-center rounded-2xl border-2 border-dashed border-gray-200 bg-sand-50 px-4 py-8 text-center transition hover:border-forest-300 hover:bg-white"
        :class="dragging ? 'border-forest-600 bg-white' : ''"
        x-on:click="openPicker()"
        x-on:dragover.prevent="dragging = true"
        x-on:dragleave.prevent="dragging = false"
        x-on:drop.prevent="onDrop($event)"
    >
        <span class="text-2xl text-forest-800">+</span>
        <span class="mt-1 text-sm font-semibold text-forest-800">Add photos & videos</span>
        <span class="mt-1 text-xs text-gray-500">Tap to choose, or drag and drop on desktop</span>
    </button>

    <input
        x-ref="picker"
        type="file"
        class="sr-only"
        multiple
        accept="image/jpeg,image/png,image/webp,video/mp4,video/webm,video/quicktime"
        x-on:change="onPickerChange($event)"
    >

    @if($mapFirst)
        <input x-ref="featured" type="file" name="{{ $featuredName }}" data-field-name="{{ $featuredName }}" class="hidden" accept="image/jpeg,image/png,image/webp" data-skip-preview tabindex="-1">
    @endif

    <input x-ref="images" type="file" name="{{ $imagesName }}[]" data-field-name="{{ $imagesName }}[]" class="hidden" multiple accept="image/jpeg,image/png,image/webp" data-skip-preview tabindex="-1">
    <input x-ref="videos" type="file" name="{{ $videosName }}[]" data-field-name="{{ $videosName }}[]" class="hidden" multiple accept="video/mp4,video/webm,video/quicktime" data-skip-preview tabindex="-1">

    <p class="mt-2 text-sm text-gray-500" x-show="items.length" x-cloak>
        <span x-text="imageCount()"></span> photos · <span x-text="videoCount()"></span> videos
    </p>

    <ul class="mt-3 grid grid-cols-3 gap-3 sm:grid-cols-4" x-show="items.length" x-cloak>
        <template x-for="item in items" :key="item.id">
            <li class="relative overflow-hidden rounded-xl bg-gray-900">
                <img x-show="item.kind === 'image'" :src="item.url" alt="" class="h-28 w-full object-cover">
                <video x-show="item.kind === 'video'" :src="item.url" class="h-28 w-full object-cover" muted playsinline></video>
                <button
                    type="button"
                    class="absolute right-1 top-1 rounded-full bg-black/70 px-2 py-0.5 text-xs font-semibold text-white"
                    x-on:click="removeItem(item.id)"
                >
                    Remove
                </button>
            </li>
        </template>
    </ul>

    <p class="mt-2 text-sm text-red-600" x-show="error" x-text="error" x-cloak></p>

    <x-input-error class="mt-2" :messages="array_merge(
        filled($featuredName) ? $errors->get($featuredName) : [],
        $errors->get($imagesName),
        $errors->get($imagesName.'.*'),
        $errors->get($videosName),
        $errors->get($videosName.'.*'),
    )" />
</div>
