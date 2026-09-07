@php
    $leaders = \App\Models\User::query()
        ->where('status', true)
        ->orderByDesc('score')
        ->orderBy('id')
        ->limit(5)
        ->get();
    $ads = demo_ads();
@endphp

<aside class="hidden w-72 shrink-0 xl:block">
    <div class="sticky top-20 space-y-4">
        @foreach($ads as $ad)
            <x-sidebar-ad :ad="$ad" />
        @endforeach

        <section class="sk-card p-4">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-forest-900">Top scores</h2>
                <a href="{{ route('leaderboard.index') }}" class="text-xs font-semibold text-forest-700 hover:underline">View all</a>
            </div>
            <ul class="space-y-3">
                @foreach($leaders as $leader)
                    <li class="flex items-center justify-between gap-3 text-sm">
                        <a href="{{ route('users.show', $leader) }}" class="flex min-w-0 items-center gap-2 font-medium text-forest-900 hover:underline">
                            <x-user-avatar :user="$leader" size="sm" />
                            <span class="truncate">{{ $leader->name }}</span>
                        </a>
                        <span class="shrink-0 text-gray-500">{{ number_format($leader->score) }}</span>
                    </li>
                @endforeach
            </ul>
        </section>
    </div>
</aside>
