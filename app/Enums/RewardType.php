<?php

namespace App\Enums;

enum RewardType: string
{
    case ProfileVisibilityCredit = 'profile_visibility_credit';

    public function label(): string
    {
        return match ($this) {
            self::ProfileVisibilityCredit => 'Profile visibility credit',
        };
    }
}
