<?php

namespace App\Enums;

enum PostBoostSource: string
{
    case Request = 'request';
    case AdminGrant = 'admin_grant';

    public function label(): string
    {
        return match ($this) {
            self::Request => 'User request',
            self::AdminGrant => 'Admin grant',
        };
    }
}
