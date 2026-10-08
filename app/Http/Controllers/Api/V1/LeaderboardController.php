<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\SerializesApiContent;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class LeaderboardController extends Controller
{
    use SerializesApiContent;

    public function index(): JsonResponse
    {
        $users = User::query()
            ->withExists([
                'greenTickVerifications as has_active_green_tick' => function ($query): void {
                    $query->currentlyActive();
                },
            ])
            ->where('status', true)
            ->orderByDesc('score')
            ->orderBy('id')
            ->paginate(20);

        return response()->json([
            'data' => $users->getCollection()->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'role' => $user->role,
                'score' => (int) $user->score,
                'avatar_url' => $user->avatarUrl(),
                'has_green_tick' => $user->hasActiveGreenTick(),
            ])->values(),
            'meta' => $this->paginationMeta($users),
        ]);
    }
}
