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

    /**
     * @return array{
     *     id: string,
     *     status: string,
     *     url: ?string,
     *     payment_status: string,
     *     order_id: ?string,
     *     client_reference_id: ?string,
     *     payment_intent: ?string,
     *     payment_intent_status: ?string
     * }|null
     */
    public function retrieveCheckoutSession(string $sessionId): ?array;

    public function expireSession(string $sessionId): void;
}
