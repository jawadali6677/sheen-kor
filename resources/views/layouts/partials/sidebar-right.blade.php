@php
    $leaders = \App\Models\User::query()
        ->where('status', true)
        ->orderByDesc('score')
        ->orderBy('id')
        ->limit(5)
        ->get();
    $ads = app(\App\Actions\PlaceFeedAds::class)->sidebarCards();
    $communityImpact = [
        'stories' => \App\Models\Post::query()->where('status', 'published')->count(),
        'alerts_fixed' => \App\Models\Alert::query()->where('status', 'fixed')->count(),
        'members' => \App\Models\User::query()->where('status', true)->count(),
    ];
@endphp

<aside class="hidden w-64 shrink-0 lg:block xl:w-72">
    <div class="sticky top-20 space-y-4">
        @if($communityImpact)
            <section class="overflow-hidden rounded-2xl border border-emerald-100 bg-gradient-to-br from-emerald-50 via-white to-lime-50 p-4 shadow-card">
                <div class="mb-3 flex items-center gap-2">
                    <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-white text-forest-700 shadow-soft">
                        <x-application-logo class="h-4 w-4" />
                    </span>
                    <h2 class="text-sm font-semibold text-forest-900">Community Impact</h2>
                </div>
                <div class="grid grid-cols-3 gap-2 text-center">
                    <div class="rounded-xl bg-white/80 px-1.5 py-3">
                        <span class="mx-auto mb-1 flex h-7 w-7 items-center justify-center rounded-full bg-emerald-50 text-emerald-600">
                            <svg class="h-4 w-4" viewBox="0 0 32 32" fill="currentColor" aria-hidden="true"><path d="M16 3c.6 4.2 2.4 7.4 6.4 10.2-3.1.6-5.3 2.1-6.4 5.3-1.1-3.2-3.3-4.7-6.4-5.3C13.6 10.4 15.4 7.2 16 3Z"/><path d="M16 13.8c4.6 1.8 7.7 5 8.8 10.2-3.7-1.2-6.4-.6-8.8 2.4-2.4-3-5.1-3.6-8.8-2.4 1.1-5.2 4.2-8.4 8.8-10.2Z" opacity=".85"/></svg>
                        </span>
                        <p class="text-lg font-semibold text-forest-900" data-community-stat="stories">{{ number_format($communityImpact['stories']) }}</p>
                        <p class="mt-1 text-[11px] font-medium leading-4 text-gray-500">Stories</p>
                    </div>
                    <div class="rounded-xl bg-white/80 px-1.5 py-3">
                        <span class="mx-auto mb-1 flex h-7 w-7 items-center justify-center rounded-full bg-red-50 text-red-500">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.3 4.2 2.6 18a2 2 0 0 0 1.7 3h15.4a2 2 0 0 0 1.7-3L13.7 4.2a2 2 0 0 0-3.4 0Z"/></svg>
                        </span>
                        <p class="text-lg font-semibold text-forest-900" data-community-stat="alerts-fixed">{{ number_format($communityImpact['alerts_fixed']) }}</p>
                        <p class="mt-1 text-[11px] font-medium leading-4 text-gray-500">Alerts fixed</p>
                    </div>
                    <div class="rounded-xl bg-white/80 px-1.5 py-3">
                        <span class="mx-auto mb-1 flex h-7 w-7 items-center justify-center rounded-full bg-emerald-50 text-emerald-600">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16 19v-1a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v1M9.5 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm9.5 8v-1a3.5 3.5 0 0 0-2.6-3.38M16 5.13a3 3 0 0 1 0 5.75"/></svg>
                        </span>
                        <p class="text-lg font-semibold text-forest-900" data-community-stat="members">{{ number_format($communityImpact['members']) }}</p>
                        <p class="mt-1 text-[11px] font-medium leading-4 text-gray-500">Active members</p>
                    </div>
                </div>
            </section>
        @endif

        @foreach($ads as $ad)
            <x-sidebar-ad :ad="$ad" sidebar />
        @endforeach

        @if($leaders->isNotEmpty())
            <section class="sk-card p-4">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-forest-900">Top contributors</h2>
                    <a href="{{ route('leaderboard.index') }}" class="text-xs font-semibold text-forest-700 hover:underline">View all</a>
                </div>
                <ul class="space-y-3">
                    @foreach($leaders as $leader)
                        <li class="flex items-center justify-between gap-3 text-sm">
                            <a href="{{ route('users.show', $leader) }}" class="flex min-w-0 items-center gap-2 font-medium text-forest-900 hover:underline">
                                <x-user-avatar :user="$leader" size="sm" />
                                <span class="truncate">{{ $leader->name }}</span>
                            </a>
                            <span class="shrink-0 text-gray-500">{{ number_format($leader->score) }} pts</span>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>
</aside>
