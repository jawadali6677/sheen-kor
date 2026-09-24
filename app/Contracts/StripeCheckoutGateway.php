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

    /**
     * @return object{id: string, payment_status: ?string, metadata: array<string, mixed>, client_reference_id: ?string, payment_intent: ?string}|null
     */
    public function retrieveCheckoutSession(string $sessionId): ?object;
}
