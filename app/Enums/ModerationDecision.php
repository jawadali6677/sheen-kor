<?php

namespace App\Enums;

enum ModerationDecision: string
{
    case Allow = 'allow';
    case Reject = 'reject';
    case Review = 'review';
}
