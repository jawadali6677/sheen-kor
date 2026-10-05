<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-forest-900">Review stories</h2>
    </x-slot>

    <div class="py-8" x-data="adminPostsQueue">
        <div class="mx-auto max-w-6xl sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="mb-6 rounded-2xl bg-emerald-50 p-4 text-forest-800">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="mb-6 rounded-2xl bg-red-50 p-4 text-red-700">{{ session('error') }}</div>
            @endif

            <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4" data-admin-posts-counts>
                <a href="{{ route('admin.posts.index', ['status' => 'pending']) }}" data-admin-posts-filter="pending" class="sk-card p-4 ring-1 ring-gray-100 {{ $status === 'pending' ? 'ring-2 ring-amber-400' : '' }}">
                    <p class="text-2xl font-semibold text-amber-700" data-count="pending">{{ number_format($counts['pending']) }}</p>
                    <p class="text-sm text-gray-600">Pending</p>
                </a>
                <a href="{{ route('admin.posts.index', ['status' => 'published']) }}" data-admin-posts-filter="published" class="sk-card p-4 ring-1 ring-gray-100 {{ $status === 'published' ? 'ring-2 ring-forest-400' : '' }}">
                    <p class="text-2xl font-semibold text-forest-800" data-count="published">{{ number_format($counts['published']) }}</p>
                    <p class="text-sm text-gray-600">Published</p>
                </a>
                <a href="{{ route('admin.posts.index', ['status' => 'rejected']) }}" data-admin-posts-filter="rejected" class="sk-card p-4 ring-1 ring-gray-100 {{ $status === 'rejected' ? 'ring-2 ring-red-300' : '' }}">
                    <p class="text-2xl font-semibold text-red-700" data-count="rejected">{{ number_format($counts['rejected']) }}</p>
                    <p class="text-sm text-gray-600">Rejected</p>
                </a>
                <a href="{{ route('admin.posts.index', ['status' => 'all']) }}" data-admin-posts-filter="all" class="sk-card p-4 ring-1 ring-gray-100 {{ $status === 'all' ? 'ring-2 ring-gray-400' : '' }}">
                    <p class="text-2xl font-semibold text-gray-800" data-count="total">{{ number_format($counts['total']) }}</p>
                    <p class="text-sm text-gray-600">Total</p>
                </a>
            </div>

            <div class="mb-4 flex flex-wrap gap-2 text-sm" data-admin-posts-tabs>
                @foreach(['all' => 'All', 'pending' => 'Pending', 'published' => 'Published', 'rejected' => 'Rejected'] as $value => $label)
                    <a
                        href="{{ route('admin.posts.index', array_filter(['status' => $value, 'q' => $search])) }}"
                        data-admin-posts-filter="{{ $value }}"
                        class="rounded-full px-3 py-1 {{ $status === $value ? 'bg-forest-800 text-white' : 'bg-white text-gray-700 ring-1 ring-gray-200' }}"
                    >{{ $label }}</a>
                @endforeach
            </div>

            <form method="GET" action="{{ route('admin.posts.index') }}" class="mb-4 flex flex-wrap gap-2" data-admin-posts-search>
                <input type="hidden" name="status" value="{{ $status }}">
                <input
                    type="search"
                    name="q"
                    value="{{ $search }}"
                    placeholder="Search title, content, or author"
                    class="w-full max-w-md rounded-md border-gray-300 text-sm sm:w-80"
                >
                <button type="submit" class="btn-primary text-sm">Search</button>
            </form>

            <div class="relative">
                <div
                    x-show="loading"
                    x-cloak
                    class="absolute inset-0 z-10 flex items-center justify-center rounded-lg bg-white/70 text-sm font-medium text-forest-800"
                >Loading…</div>
                <div x-ref="results">
                    @include('admin.posts.partials.results')
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
