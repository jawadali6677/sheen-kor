<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function index(): View
    {
        $this->authorizeManage();

        $roles = Role::query()
            ->withCount('permissionRecords')
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->get();

        return view('admin.roles.index', compact('roles'));
    }

    public function create(): View
    {
        $this->authorizeManage();

        return view('admin.roles.create', [
            'permissions' => auth()->user()->assignablePermissions(),
            'selected' => [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManage();

        $validated = $this->validatedRole($request);

        $role = Role::query()->create([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'description' => $validated['description'] ?? null,
            'is_system' => false,
        ]);

        $role->syncPermissions($validated['permissions'] ?? []);

        return redirect()
            ->route('admin.roles.index')
            ->with('success', $role->name.' was created.');
    }

    public function edit(Role $role): View
    {
        $this->authorizeManage();

        return view('admin.roles.edit', [
            'role' => $role,
            'permissions' => auth()->user()->assignablePermissions(),
            'selected' => $role->permissionValues(),
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $this->authorizeManage();

        $validated = $this->validatedRole($request, $role);

        $role->update([
            'name' => $validated['name'],
            'slug' => $role->is_system ? $role->slug : $validated['slug'],
            'description' => $validated['description'] ?? null,
        ]);

        if (! $role->isAdmin()) {
            $role->syncPermissions($validated['permissions'] ?? []);
        }

        return redirect()
            ->route('admin.roles.index')
            ->with('success', $role->name.' was updated.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        $this->authorizeManage();

        if ($role->is_system) {
            return back()->with('error', 'System roles cannot be deleted.');
        }

        if ($role->users()->exists()) {
            return back()->with('error', 'Reassign users before deleting this role.');
        }

        $role->delete();

        return back()->with('success', 'The role was deleted.');
    }

    private function authorizeManage(): void
    {
        abort_unless(auth()->user()?->hasPermission(Permission::ManageRoles), 403);
    }

    /**
     * @return array{name: string, slug: string, description?: string|null, permissions?: list<string>}
     */
    private function validatedRole(Request $request, ?Role $role = null): array
    {
        $slug = Str::slug((string) $request->input('slug', $request->input('name')));

        $request->merge(['slug' => $slug]);

        $assignable = array_map(
            fn (Permission $permission): string => $permission->value,
            $request->user()->assignablePermissions(),
        );

        return $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'slug' => [
                'required',
                'string',
                'max:50',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('roles', 'slug')->ignore($role),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in($assignable)],
        ]);
    }
}
