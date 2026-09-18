<?php

namespace App\Support;

use App\Contracts\RewardedAdVerifier;
use App\Models\RewardedAdSession;

class UnavailableRewardedAdVerifier implements RewardedAdVerifier
{
    public function verify(RewardedAdSession $session, ?string $providerReference): bool
    {
        return false;
    }
}
