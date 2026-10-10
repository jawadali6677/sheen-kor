<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\FollowUser;
use App\Actions\UnfollowUser;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FollowController extends Controller
{
    public function store(Request $request, User $user, FollowUser $follow): JsonResponse
    {
        $this->authorize('follow', $user);

        $follow->handle($request->user(), $user);

        return response()->json([
            'following' => true,
        ]);
    }

    public function destroy(Request $request, User $user, UnfollowUser $unfollow): JsonResponse
    {
        $this->authorize('unfollow', $user);

        $unfollow->handle($request->user(), $user);

        return response()->json([
            'following' => false,
        ]);
    }
}
