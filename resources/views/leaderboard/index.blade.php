<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Leaderboard
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-left">
                        <tr>
                            <th class="px-4 py-3">Rank</th>
                            <th class="px-4 py-3">Member</th>
                            <th class="px-4 py-3">Role</th>
                            <th class="px-4 py-3 text-right">Points</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($users as $index => $member)
                            <tr class="border-t {{ $member->is(auth()->user()) ? 'bg-green-50' : '' }}">
                                <td class="px-4 py-3">{{ $users->firstItem() + $index }}</td>
                                <td class="px-4 py-3 font-medium">
                                    <a href="{{ route('users.show', $member) }}">{{ $member->name }}</a>
                                </td>
                                <td class="px-4 py-3 text-gray-500">{{ $member->roleLabel() }}</td>
                                <td class="px-4 py-3 text-right font-semibold">{{ number_format($member->score) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-6">
                {{ $users->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
