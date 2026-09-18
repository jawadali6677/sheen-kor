<?php

namespace App\Enums;

enum RewardedAdStatus: string
{
    case Started = 'started';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
