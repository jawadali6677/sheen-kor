@php
    $field = $field ?? 'videos';
    $inputId = $inputId ?? $field;
@endphp

<div class="mb-6">
    <label for="{{ $inputId }}" class="block font-medium text-sm text-gray-700">
        {{ $label ?? 'Short videos (optional)' }}
    </label>
    <input
        type="file"
        name="{{ $field }}[]"
        id="{{ $inputId }}"
        multiple
        accept="video/mp4,video/webm,video/quicktime"
        class="mt-1 block w-full"
    >
    <p class="text-sm text-gray-500 mt-1">
        {{ $hint ?? 'Add short clips (MP4, WebM, or MOV). Up to 3 videos, 20 MB each.' }}
    </p>
    <x-input-error class="mt-2" :messages="array_merge($errors->get($field), $errors->get($field.'.*'))" />
</div>
