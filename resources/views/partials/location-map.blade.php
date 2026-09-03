@php
    $mapId = $mapId ?? 'location-map-'.uniqid();
    $latName = $latName ?? 'latitude';
    $lngName = $lngName ?? 'longitude';
    $nameField = $nameField ?? 'location_name';
    $nameLabel = $nameLabel ?? 'Location';
    $requiredName = $requiredName ?? false;
    $readonly = $readonly ?? false;
    $latValue = old($latName, $lat ?? '');
    $lngValue = old($lngName, $lng ?? '');
    $nameValue = old($nameField, $name ?? '');
@endphp

<div class="location-picker mb-6">
    @unless($readonly)
        <label class="block font-medium text-sm text-gray-700 mb-1">{{ $nameLabel }}</label>
        <div class="flex gap-2 mb-2">
            <input
                type="search"
                id="{{ $mapId }}-search"
                class="block w-full border-gray-300 rounded-md shadow-sm"
                placeholder="Search a place, then click the map"
                autocomplete="off"
            >
            <button type="button" id="{{ $mapId }}-search-btn" class="px-3 py-2 bg-gray-800 text-white rounded whitespace-nowrap">
                Find
            </button>
        </div>
        <button type="button" id="{{ $mapId }}-locate" class="mb-2 text-sm text-blue-700">
            Use my current location
        </button>
    @endunless

    <div
        id="{{ $mapId }}"
        class="location-map border js-location-map"
        data-lat-selector="{{ $readonly ? '' : '#'.$latName }}"
        data-lng-selector="{{ $readonly ? '' : '#'.$lngName }}"
        data-name-selector="{{ $readonly ? '' : '#'.$nameField }}"
        data-search-selector="#{{ $mapId }}-search"
        data-search-button-selector="#{{ $mapId }}-search-btn"
        data-locate-selector="#{{ $mapId }}-locate"
        data-initial-lat="{{ $latValue }}"
        data-initial-lng="{{ $lngValue }}"
        data-readonly="{{ $readonly ? '1' : '0' }}"
    ></div>

    @unless($readonly)
        <input
            type="text"
            name="{{ $nameField }}"
            id="{{ $nameField }}"
            value="{{ $nameValue }}"
            class="mt-3 block w-full border-gray-300 rounded-md shadow-sm"
            placeholder="Place name (filled from the map, you can edit it)"
            @if($requiredName) required @endif
        >
        <input type="hidden" name="{{ $latName }}" id="{{ $latName }}" value="{{ $latValue }}">
        <input type="hidden" name="{{ $lngName }}" id="{{ $lngName }}" value="{{ $lngValue }}">
        <p class="text-sm text-gray-500 mt-1">Click the map, search, or use your current location. Coordinates are filled for you.</p>
    @else
        @if($nameValue)
            <p class="text-sm text-gray-600 mt-2">{{ $nameValue }}</p>
        @endif
    @endunless
</div>
