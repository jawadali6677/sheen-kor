<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Role;
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
            ->with(['assignedRole', 'extraPermissionRecords'])
            ->orderByDesc('score')
            ->orderBy('name')
            ->paginate(20);

        return view('admin.users.index', [
            'users' => $users,
            'roles' => Role::query()->orderBy('name')->get(),
            'permissions' => auth()->user()->assignablePermissions(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $assignable = array_map(
            fn (Permission $permission): string => $permission->value,
            $request->user()->assignablePermissions(),
        );

        $validated = $request->validate([
            'role' => ['required', 'string', Rule::exists('roles', 'slug')],
            'status' => ['required', 'boolean'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in($assignable)],
        ]);

        if ($validated['role'] === Role::ADMIN && ! $request->user()->isAdmin()) {
            return back()->with('error', 'Only an admin can assign the admin role.');
        }

        if ($user->is($request->user()) && ! $validated['status']) {
            return back()->with('error', 'You cannot disable your own account.');
        }

        if (! $request->user()->can('changeRole', [$user, $validated['role']])) {
            return back()->with('error', 'There must be at least one active admin.');
        }

        $user->forceFill([
            'role' => $validated['role'],
            'status' => $validated['status'],
        ])->save();

        $user->syncExtraPermissions($validated['permissions'] ?? []);

        return back()->with('success', $user->name.' was updated.');
    }
}
