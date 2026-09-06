<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', User::class);

        $users = User::query()
            ->orderByDesc('score')
            ->orderBy('name')
            ->paginate(20);

        return view('admin.users.index', [
            'users' => $users,
            'roles' => Role::cases(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $validated = $request->validate([
            'role' => ['required', Rule::enum(Role::class)],
            'status' => ['required', 'boolean'],
        ]);

        $newRole = Role::from($validated['role']);

        if ($user->is($request->user()) && ! $validated['status']) {
            return back()->with('error', 'You cannot disable your own account.');
        }

        if (! $request->user()->can('changeRole', [$user, $newRole])) {
            return back()->with('error', 'There must be at least one active admin.');
        }

        $user->forceFill([
            'role' => $newRole,
            'status' => $validated['status'],
        ])->save();

        return back()->with('success', $user->name.' was updated.');
    }
}
