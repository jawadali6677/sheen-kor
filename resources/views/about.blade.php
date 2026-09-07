<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold text-forest-900">About Sheen Kor</h1>
    </x-slot>

    <article class="sk-card p-8 sm:p-10">
        <p class="text-sm font-semibold uppercase tracking-wide text-forest-700">Our community</p>
        <h2 class="mt-2 text-3xl font-bold text-forest-900">A modern community for a greener environment</h2>
        <div class="mt-6 space-y-4 text-gray-600">
            <p>Sheen Kor is a place where people share photos, write stories, give practical tips, and report environmental problems so neighborhoods can respond together.</p>
            <p>Members follow activity around them, support alerts until they are resolved, and celebrate the people who take action. It is social, local, and focused on the places we live.</p>
            <p>Join to post, comment, and message other members — or start by reading public stories and alerts.</p>
        </div>
        <div class="mt-8 flex flex-wrap gap-3">
            <a href="{{ route('register') }}" class="btn-accent text-forest-900">Join Sheen Kor</a>
            <a href="{{ route('posts.index') }}" class="btn-secondary">Explore stories</a>
        </div>
    </article>
</x-app-layout>
