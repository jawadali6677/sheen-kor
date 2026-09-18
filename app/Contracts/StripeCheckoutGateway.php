<?php

namespace App\Contracts;

use App\Models\User;

interface StripeCheckoutGateway
{
    /**
     * @param  array<string, mixed>  $sessionOptions
     * @return object{id: string, url: ?string}|null
     */
    public function createOneOffCheckout(User $user, int $amountCents, string $name, array $sessionOptions): ?object;

    public function expireSession(string $sessionId): void;
}
