<?php

namespace App\Http\Controllers;

use App\Actions\FollowUser;
use App\Actions\UnfollowUser;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FollowController extends Controller
{
    public function store(Request $request, User $user, FollowUser $follow): RedirectResponse
    {
        $this->authorize('follow', $user);

        $follow->handle($request->user(), $user);

        return back();
    }

    public function destroy(Request $request, User $user, UnfollowUser $unfollow): RedirectResponse
    {
        $this->authorize('unfollow', $user);

        $unfollow->handle($request->user(), $user);

        return back();
    }
}
