<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $user = auth()->user();
        $user->load('assignedRole');

        $events = $user->scoreEvents()
            ->latest()
            ->limit(10)
            ->get();

        $leaders = User::query()
            ->where('status', true)
            ->orderByDesc('score')
            ->orderBy('id')
            ->limit(8)
            ->get();

        return view('dashboard', [
            'user' => $user,
            'events' => $events,
            'leaders' => $leaders,
        ]);
    }
}
