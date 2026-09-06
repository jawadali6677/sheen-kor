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
    case ViewAnalytics = 'analytics.view';
}
