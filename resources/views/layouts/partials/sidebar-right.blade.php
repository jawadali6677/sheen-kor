@php
    $homeFeed = request()->routeIs('posts.index');
    $leaders = \App\Models\User::query()
        ->where('status', true)
        ->orderByDesc('score')
        ->orderBy('id')
        ->limit(5)
        ->get();
    $ads = app(\App\Actions\PlaceFeedAds::class)->sidebarCards();
    $communityImpact = null;

    if ($homeFeed) {
        $communityImpact = [
            'stories' => \App\Models\Post::query()->where('status', 'published')->count(),
            'alerts_fixed' => \App\Models\Alert::query()->where('status', 'fixed')->count(),
            'members' => \App\Models\User::query()->where('status', true)->count(),
        ];
    }
@endphp

<aside class="{{ $homeFeed ? 'hidden w-64 shrink-0 lg:block xl:w-72' : 'hidden w-64 shrink-0 xl:block' }}">
    <div class="sticky top-20 space-y-4">
        @if($communityImpact)
            <section class="sk-card p-4">
                <div class="mb-3 flex items-center gap-2">
                    <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-forest-50 text-forest-700">
                        <x-application-logo class="h-4 w-4" />
                    </span>
                    <h2 class="text-sm font-semibold text-forest-900">Community Impact</h2>
                </div>
                <div class="grid grid-cols-3 gap-2 text-center">
                    <div class="rounded-xl bg-sand-50 px-1.5 py-3">
                        <p class="text-lg font-semibold text-forest-900" data-community-stat="stories">{{ number_format($communityImpact['stories']) }}</p>
                        <p class="mt-1 text-[11px] font-medium leading-4 text-gray-500">Stories</p>
                    </div>
                    <div class="rounded-xl bg-sand-50 px-1.5 py-3">
                        <p class="text-lg font-semibold text-forest-900" data-community-stat="alerts-fixed">{{ number_format($communityImpact['alerts_fixed']) }}</p>
                        <p class="mt-1 text-[11px] font-medium leading-4 text-gray-500">Alerts fixed</p>
                    </div>
                    <div class="rounded-xl bg-sand-50 px-1.5 py-3">
                        <p class="text-lg font-semibold text-forest-900" data-community-stat="members">{{ number_format($communityImpact['members']) }}</p>
                        <p class="mt-1 text-[11px] font-medium leading-4 text-gray-500">Active members</p>
                    </div>
                </div>
            </section>
        @endif

        @foreach($ads as $ad)
            <x-sidebar-ad :ad="$ad" :sidebar="$homeFeed" />
        @endforeach

        @if($leaders->isNotEmpty() || ! $homeFeed)
            <section class="sk-card p-4">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-forest-900">{{ $homeFeed ? 'Top contributors' : 'Top scores' }}</h2>
                    <a href="{{ route('leaderboard.index') }}" class="text-xs font-semibold text-forest-700 hover:underline">View all</a>
                </div>
                <ul class="space-y-3">
                    @foreach($leaders as $leader)
                        <li class="flex items-center justify-between gap-3 text-sm">
                            <a href="{{ route('users.show', $leader) }}" class="flex min-w-0 items-center gap-2 font-medium text-forest-900 hover:underline">
                                <x-user-avatar :user="$leader" size="sm" />
                                <span class="truncate">{{ $leader->name }}</span>
                            </a>
                            <span class="shrink-0 text-gray-500">{{ number_format($leader->score) }}{{ $homeFeed ? ' pts' : '' }}</span>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>
</aside>
