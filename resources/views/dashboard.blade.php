<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <p class="text-gray-900 text-lg font-semibold">{{ $user->name }}</p>
                <p class="text-sm text-gray-500">{{ $user->role->label() }} · {{ number_format($user->score) }} points</p>
            </div>

            <div class="grid gap-6 md:grid-cols-2">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <h3 class="font-semibold text-gray-900 mb-4">Your recent points</h3>
                    @forelse($events as $event)
                        <div class="flex justify-between text-sm py-2 border-b last:border-0">
                            <span>{{ $event->reason->label() }}</span>
                            <span class="font-semibold text-green-700">+{{ $event->points }}</span>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">Post a story, report an alert, or fix an alert to earn points.</p>
                    @endforelse
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="font-semibold text-gray-900">Top scores</h3>
                        <a href="{{ route('leaderboard.index') }}" class="text-sm text-blue-700">View all</a>
                    </div>
                    @foreach($leaders as $leader)
                        <div class="flex justify-between text-sm py-2 border-b last:border-0">
                            <span>{{ $leader->name }}</span>
                            <span>{{ number_format($leader->score) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
