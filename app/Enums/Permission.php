<?php

namespace App\Enums;

enum Permission: string
{
    case CreatePosts = 'posts.create';
    case ModeratePosts = 'posts.moderate';
    case CreateAlerts = 'alerts.create';
    case ModerateAlerts = 'alerts.moderate';
    case TakeActionOnAlerts = 'alerts.take_action';
    case MarkAlertsFixed = 'alerts.mark_fixed';
    case ModerateComments = 'comments.moderate';
    case ManageUsers = 'users.manage';
    case ManageRoles = 'roles.manage';
    case ViewAnalytics = 'analytics.view';

    public function label(): string
    {
        return match ($this) {
            self::CreatePosts => 'Create stories',
            self::ModeratePosts => 'Edit or delete any story',
            self::CreateAlerts => 'Report alerts',
            self::ModerateAlerts => 'Edit or delete any alert',
            self::TakeActionOnAlerts => 'Take action on alerts',
            self::MarkAlertsFixed => 'Mark alerts as fixed',
            self::ModerateComments => 'Delete any comment',
            self::ManageUsers => 'Manage users and assign roles',
            self::ManageRoles => 'Create roles and set their permissions',
            self::ViewAnalytics => 'View analytics',
        };
    }
}
