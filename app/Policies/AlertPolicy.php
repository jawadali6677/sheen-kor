<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Alert;
use App\Models\User;

class AlertPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Alert $alert): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::CreateAlerts);
    }

    public function update(User $user, Alert $alert): bool
    {
        return $alert->user_id === $user->id
            || $user->hasPermission(Permission::ModerateAlerts);
    }

    public function delete(User $user, Alert $alert): bool
    {
        return $this->update($user, $alert);
    }

    public function takeAction(User $user, Alert $alert): bool
    {
        return $user->hasPermission(Permission::TakeActionOnAlerts);
    }

    public function markFixed(User $user, Alert $alert): bool
    {
        if (! $user->hasPermission(Permission::MarkAlertsFixed)) {
            return false;
        }

        if ($alert->canBeFixedBy($user->id)) {
            return true;
        }

        return $user->hasPermission(Permission::ModerateAlerts)
            && $alert->isInProgress();
    }
}
