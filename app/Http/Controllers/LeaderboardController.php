<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\View\View;

class LeaderboardController extends Controller
{
    public function index(): View
    {
        $users = User::query()
            ->where('status', true)
            ->orderByDesc('score')
            ->orderBy('id')
            ->paginate(20);

        return view('leaderboard.index', compact('users'));
    }
}
