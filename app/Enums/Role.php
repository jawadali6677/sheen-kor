<?php

namespace App\Enums;

enum Role: string
{
    case User = 'user';
    case Moderator = 'moderator';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::User => 'User',
            self::Moderator => 'Moderator',
            self::Admin => 'Admin',
        };
    }

    /**
     * @return list<Permission>
     */
    public function permissions(): array
    {
        $member = [
            Permission::CreatePosts,
            Permission::CreateAlerts,
            Permission::TakeActionOnAlerts,
            Permission::MarkAlertsFixed,
        ];

        return match ($this) {
            self::User => $member,
            self::Moderator => [
                ...$member,
                Permission::ModeratePosts,
                Permission::ModerateAlerts,
                Permission::ModerateComments,
            ],
            self::Admin => Permission::cases(),
        };
    }

    public function has(Permission $permission): bool
    {
        return in_array($permission, $this->permissions(), true);
    }
}
