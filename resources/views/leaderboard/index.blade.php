<x-app-layout>
    <div class="space-y-4">
        <div>
            <h1 class="text-xl font-semibold text-forest-900">Scores</h1>
            <p class="mt-1 text-sm text-gray-500">Members leading the community.</p>
        </div>

        <div class="sk-card overflow-hidden">
            <table class="min-w-full text-sm">
                <thead class="bg-emerald-50 text-left text-forest-800">
                    <tr>
                        <th class="px-4 py-3 font-semibold">Rank</th>
                        <th class="px-4 py-3 font-semibold">Member</th>
                        <th class="px-4 py-3 font-semibold">Role</th>
                        <th class="px-4 py-3 text-right font-semibold">Points</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($users as $index => $member)
                        <tr class="border-t border-gray-100 {{ $member->is(auth()->user()) ? 'bg-emerald-50' : '' }}">
                            <td class="px-4 py-3">{{ $users->firstItem() + $index }}</td>
                            <td class="px-4 py-3 font-medium">
                                <a href="{{ route('users.show', $member) }}" class="text-forest-900 hover:underline">{{ $member->name }}</a>
                            </td>
                            <td class="px-4 py-3 text-gray-500">{{ $member->roleLabel() }}</td>
                            <td class="px-4 py-3 text-right font-semibold text-forest-800">{{ number_format($member->score) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div>
            {{ $users->links() }}
        </div>
    </div>
</x-app-layout>
