<x-app-layout>
    <div class="space-y-4">
        <h1 class="text-xl font-semibold text-forest-900">{{ __('Dashboard') }}</h1>

        <div class="sk-card flex items-center gap-4 p-6">
            <x-user-avatar :user="$user" />
            <div>
                <p class="text-lg font-semibold text-forest-900">
                    <a href="{{ route('users.show', $user) }}" class="hover:underline">{{ $user->name }}</a>
                </p>
                <p class="text-sm text-gray-500">{{ $user->roleLabel() }} · {{ number_format($user->score) }} points</p>
                <a href="{{ route('profile.edit') }}" class="text-sm font-semibold text-forest-700 hover:underline">Complete your profile</a>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <div class="sk-card p-6">
                <h2 class="mb-4 font-semibold text-forest-900">Your recent points</h2>
                @forelse($events as $event)
                    <div class="flex justify-between border-b py-2 text-sm last:border-0">
                        <span>{{ $event->reason->label() }}</span>
                        <span class="font-semibold text-forest-700">+{{ $event->points }}</span>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">Post a story, report an alert, or fix an alert to earn points.</p>
                @endforelse
            </div>

            <div class="sk-card p-6">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="font-semibold text-forest-900">Top scores</h2>
                    <a href="{{ route('leaderboard.index') }}" class="text-sm font-semibold text-forest-700 hover:underline">View all</a>
                </div>
                @foreach($leaders as $leader)
                    <div class="flex justify-between border-b py-2 text-sm last:border-0">
                        <a href="{{ route('users.show', $leader) }}" class="font-medium text-forest-900 hover:underline">{{ $leader->name }}</a>
                        <span>{{ number_format($leader->score) }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</x-app-layout>
