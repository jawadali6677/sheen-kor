<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @auth
            @php
                $chatReverb = [
                    'key' => config('broadcasting.connections.reverb.key'),
                    'host' => config('broadcasting.connections.reverb.options.host') ?: (parse_url((string) config('app.url'), PHP_URL_HOST) ?: '127.0.0.1'),
                    'port' => (int) (config('broadcasting.connections.reverb.options.port') ?: 8080),
                    'scheme' => config('broadcasting.connections.reverb.options.scheme') ?: 'http',
                    'authEndpoint' => url('/broadcasting/auth'),
                    'csrfToken' => csrf_token(),
                ];
            @endphp
            <script>
                window.chatReverb = @json($chatReverb);
            </script>
        @endauth

        <title>{{ $title ?? config('app.name', 'SHEEN KOR') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <link href="{{ asset('css/social-feed.css') }}" rel="stylesheet">
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
        @stack('styles')
        <style>[x-cloak]{display:none !important;}</style>
    </head>
    <body class="bg-sand-50 font-sans text-gray-800 antialiased">
        <div class="{{ $fullBleed ? 'min-h-screen' : 'min-h-screen pb-20 lg:pb-0' }}">
            @include('layouts.navigation')

            @isset($header)
                <header class="border-b border-gray-100 bg-white">
                    <div class="mx-auto max-w-7xl px-4 py-5 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <main class="{{ $fullBleed ? '' : 'mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8' }}">
                @if($fullBleed || $hideSidebars)
                    {{ $slot }}
                @else
                    <div class="flex gap-6">
                        @auth
                            @include('layouts.partials.sidebar-left')
                        @endauth
                        <div class="min-w-0 flex-1">
                            {{ $slot }}
                        </div>
                        @auth
                            @include('layouts.partials.sidebar-right')
                        @endauth
                    </div>
                @endif
            </main>
        </div>

        @include('layouts.partials.mobile-nav')
        <x-confirm-dialog />

        <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
        <script src="{{ asset('js/location-map.js') }}"></script>
        <script src="{{ asset('js/post-engagement.js') }}"></script>
        <script src="{{ asset('js/image-preview.js') }}"></script>
        <div id="image-lightbox" class="image-lightbox" hidden>
            <button type="button" class="image-lightbox-close js-image-lightbox-close">Close</button>
            <img id="image-lightbox-image" alt="Preview">
        </div>
        @stack('scripts')
    </body>
</html>
