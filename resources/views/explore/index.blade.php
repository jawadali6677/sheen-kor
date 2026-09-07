<x-app-layout>
    <div class="space-y-5">
        <h1 class="text-xl font-semibold text-forest-900">Explore</h1>
        <form method="GET" action="{{ route('explore.index') }}" class="sk-card flex flex-col gap-3 p-4 sm:flex-row sm:items-end">
            <div class="flex-1">
                <label for="explore-q" class="text-xs font-semibold uppercase tracking-wide text-gray-400">Search</label>
                <input id="explore-q" type="search" name="q" value="{{ $search }}" class="sk-input" placeholder="Posts, people, alerts, categories">
            </div>
            <input type="hidden" name="tab" value="{{ $tab }}">
            <button type="submit" class="btn-primary">Search</button>
        </form>

        <div class="flex flex-wrap gap-2">
            @foreach(['posts' => 'Posts', 'people' => 'People', 'alerts' => 'Alerts', 'categories' => 'Categories'] as $key => $label)
                <a href="{{ route('explore.index', array_filter(['tab' => $key, 'q' => $search])) }}" class="rounded-full px-4 py-2 text-sm font-semibold {{ $tab === $key ? 'bg-forest-800 text-white' : 'bg-white text-forest-800 ring-1 ring-gray-200' }}">{{ $label }}</a>
            @endforeach
        </div>

        @if($tab === 'posts')
            <div class="grid gap-4 sm:grid-cols-2">
                @forelse($posts as $post)
                    <a href="{{ route('posts.show', $post) }}" class="sk-card p-4 hover:shadow-soft">
                        <p class="text-sm text-gray-500">{{ $post->user?->name }}</p>
                        <h2 class="mt-1 font-semibold text-forest-900">{{ $post->title }}</h2>
                    </a>
                @empty
                    <x-empty-state class="sm:col-span-2" title="No search results">Try another keyword or browse Home.</x-empty-state>
                @endforelse
            </div>
        @elseif($tab === 'people')
            <div class="grid gap-4 sm:grid-cols-2">
                @forelse($people as $person)
                    <a href="{{ route('users.show', $person) }}" class="sk-card flex items-center gap-3 p-4">
                        <x-user-avatar :user="$person" />
                        <div>
                            <p class="font-semibold text-forest-900">{{ $person->name }}</p>
                            @if($person->username)<p class="text-sm text-gray-500">{{ '@'.$person->username }}</p>@endif
                        </div>
                    </a>
                @empty
                    <x-empty-state class="sm:col-span-2" title="No people found">Try a name or username.</x-empty-state>
                @endforelse
            </div>
        @elseif($tab === 'alerts')
            <div class="grid gap-4 sm:grid-cols-2">
                @forelse($alerts as $alert)
                    <a href="{{ route('alerts.show', $alert) }}" class="sk-card p-4">
                        <p class="text-sm text-gray-500">{{ $alert->location_name }}</p>
                        <h2 class="mt-1 font-semibold text-forest-900">{{ $alert->title }}</h2>
                    </a>
                @empty
                    <x-empty-state class="sm:col-span-2" title="No alerts found">Try another place or keyword.</x-empty-state>
                @endforelse
            </div>
        @else
            <div class="grid gap-4 sm:grid-cols-2">
                @forelse($categories as $item)
                    <a href="{{ route('categories.show', $item) }}" class="sk-card p-4">
                        <h2 class="font-semibold text-forest-900">{{ $item->name }}</h2>
                        <p class="mt-1 text-sm text-gray-500">{{ $item->description }}</p>
                    </a>
                @empty
                    <x-empty-state class="sm:col-span-2" title="No categories found">Browse Home to see every story.</x-empty-state>
                @endforelse
            </div>
        @endif
    </div>
</x-app-layout>
