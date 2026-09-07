<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ config('app.name', 'SHEEN KOR') }}</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased">
        <div class="relative min-h-screen overflow-hidden bg-forest-900">
            <img
                src="{{ asset('images/hero/sheenkor-hero.jpg') }}"
                alt=""
                class="absolute inset-0 h-full w-full object-cover"
                onerror="this.style.display='none'; this.nextElementSibling.style.display='block';"
            >
            <div class="absolute inset-0 hidden bg-gradient-to-br from-forest-900 via-forest-800 to-forest-700" style="display:none"></div>
            <div class="absolute inset-0 bg-gradient-to-r from-forest-950/80 via-forest-950/55 to-forest-900/20"></div>

            <div class="relative z-10 flex min-h-screen flex-col">
                <header class="mx-auto flex w-full max-w-6xl items-center justify-between px-6 py-6">
                    <a href="{{ route('home') }}"><x-brand theme="light" /></a>
                    <a href="{{ route('home') }}" class="text-sm text-white/80 hover:text-white">{{ __('Back to Sheen Kor') }}</a>
                </header>
                <div class="flex flex-1 items-center justify-center px-6 pb-16">
                    <div class="w-full max-w-md rounded-3xl bg-white p-8 shadow-card">
                        {{ $slot }}
                    </div>
                </div>
            </div>
        </div>
    </body>
</html>
