<x-marketing-layout title="SHEEN KOR">
    @php
        $heroJpg = public_path('images/hero/sheenkor-hero.jpg');
        $heroSrc = file_exists($heroJpg)
            ? asset('images/hero/sheenkor-hero.jpg')
            : asset('images/hero/sheenkor-hero.svg');
    @endphp

    <div class="relative min-h-screen overflow-hidden text-white">
        <img
            src="{{ $heroSrc }}"
            alt=""
            class="absolute inset-0 h-full w-full object-cover"
        >
        <div class="absolute inset-0 bg-gradient-to-r from-forest-950/85 via-forest-950/55 to-transparent"></div>
        <div class="absolute inset-0 bg-gradient-to-t from-forest-950/50 via-transparent to-forest-950/20"></div>

        <header class="relative z-20">
            <nav class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-6 py-6" aria-label="{{ __('Guest') }}">
                <a href="{{ route('home') }}" class="shrink-0"><x-brand theme="light" /></a>
                <div class="hidden items-center gap-8 text-sm text-white/85 md:flex">
                    <a href="{{ route('home') }}" class="border-b-2 border-lime-400 pb-1 font-semibold text-white">{{ __('Home') }}</a>
                    <a href="{{ route('about') }}" class="hover:text-white">{{ __('About') }}</a>
                    <a href="{{ route('posts.index') }}" class="hover:text-white">{{ __('Blog') }}</a>
                    <a href="{{ route('tips.index') }}" class="hover:text-white">{{ __('Tips') }}</a>
                    <a href="{{ route('alerts.index') }}" class="hover:text-white">{{ __('Alerts') }}</a>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('login') }}" class="btn-ghost">{{ __('Login') }}</a>
                    <a href="{{ route('register') }}" class="btn-accent">{{ __('Sign Up') }}</a>
                </div>
            </nav>
        </header>

        <section class="relative z-10 mx-auto flex min-h-[calc(100vh-5rem)] max-w-7xl flex-col justify-center px-6 pb-24 pt-8">
            <div class="max-w-xl">
                <p class="mb-4 flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.22em] text-white/80">
                    <x-application-logo class="h-4 w-4 text-lime-400" />
                    A cleaner planet • A brighter future
                </p>
                <h1 class="text-4xl font-bold leading-tight sm:text-5xl lg:text-6xl">
                    Together for a <span class="text-lime-400">Greener Tomorrow</span>
                </h1>
                <p class="mt-6 max-w-lg text-base leading-relaxed text-white/85 sm:text-lg">
                    Share ideas, discover meaningful stories, report environmental issues and help create cleaner communities.
                </p>

                <div class="mt-8 flex flex-wrap gap-3">
                    <a href="{{ route('register') }}" class="btn-accent px-6 py-3">{{ __('Join Sheen Kor') }}</a>
                    <a href="{{ route('posts.index') }}" class="btn-ghost px-6 py-3">{{ __('Explore') }}</a>
                </div>

                <ul class="mt-10 grid grid-cols-1 gap-4 text-sm sm:grid-cols-3">
                    <li class="flex items-start gap-3">
                        <span class="mt-0.5 text-lime-400" aria-hidden="true">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M4 12h10M4 17h7"/></svg>
                        </span>
                        <span>
                            <strong class="block font-semibold">Share</strong>
                            Photos &amp; Stories
                        </span>
                    </li>
                    <li class="flex items-start gap-3">
                        <span class="mt-0.5 text-lime-400" aria-hidden="true">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M7 4h10v16H7zM7 8h10"/></svg>
                        </span>
                        <span>
                            <strong class="block font-semibold">Read</strong>
                            Articles &amp; Tips
                        </span>
                    </li>
                    <li class="flex items-start gap-3">
                        <span class="mt-0.5 text-lime-400" aria-hidden="true">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 1 0-12 0v3.2c0 .5-.2 1-.6 1.4L4 17h5m6 0a3 3 0 1 1-6 0"/></svg>
                        </span>
                        <span>
                            <strong class="block font-semibold">Report</strong>
                            Environmental Issues
                        </span>
                    </li>
                </ul>
            </div>

            <p class="absolute bottom-8 left-1/2 -translate-x-1/2 text-center text-xs uppercase tracking-widest text-white/60">
                Scroll down
            </p>
        </section>
    </div>
</x-marketing-layout>
