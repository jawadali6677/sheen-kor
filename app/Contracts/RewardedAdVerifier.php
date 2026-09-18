<?php

namespace App\Contracts;

use App\Models\RewardedAdSession;

interface RewardedAdVerifier
{
    /**
     * Return true only when a real ad network confirms this session completed.
     * The default implementation always returns false until a provider is connected.
     */
    public function verify(RewardedAdSession $session, ?string $providerReference): bool;
}
