<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Role;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::ManageUsers);
    }

    public function update(User $actor, User $user): bool
    {
        return $actor->hasPermission(Permission::ManageUsers);
    }

    public function changeRole(User $actor, User $user, string $newRole): bool
    {
        if (! $this->update($actor, $user)) {
            return false;
        }

        if ($user->role !== Role::ADMIN || $newRole === Role::ADMIN) {
            return true;
        }

        return User::query()
            ->where('role', Role::ADMIN)
            ->where('status', true)
            ->whereKeyNot($user->id)
            ->exists();
    }
}
