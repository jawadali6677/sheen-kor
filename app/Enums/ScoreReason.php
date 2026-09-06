<?php

namespace App\Enums;

enum ScoreReason: string
{
    case PostCreated = 'post_created';
    case AlertCreated = 'alert_created';
    case AlertFixed = 'alert_fixed';

    public function label(): string
    {
        return match ($this) {
            self::PostCreated => 'Posted a story',
            self::AlertCreated => 'Reported an alert',
            self::AlertFixed => 'Fixed an alert',
        };
    }
}
