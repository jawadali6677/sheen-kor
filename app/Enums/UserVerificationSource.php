<?php

namespace App\Enums;

enum UserVerificationSource: string
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
