<?php

namespace App\Enums;

enum AdEventType: string
{
    case Impression = 'impression';
    case Click = 'click';
    case RewardedStart = 'rewarded_start';
    case RewardedComplete = 'rewarded_complete';
    case RewardedFail = 'rewarded_fail';
}
