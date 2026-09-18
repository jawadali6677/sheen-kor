<?php

namespace App\Support;

use App\Contracts\StripeCheckoutGateway;
use App\Models\User;
use Laravel\Cashier\Cashier;
use Throwable;

class CashierStripeCheckoutGateway implements StripeCheckoutGateway
{
    /**
     * @param  array<string, mixed>  $sessionOptions
     */
    public function createOneOffCheckout(User $user, int $amountCents, string $name, array $sessionOptions): ?object
    {
        try {
            return $user->checkoutCharge($amountCents, $name, 1, $sessionOptions)
                ->asStripeCheckoutSession();
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    public function expireSession(string $sessionId): void
    {
        try {
            Cashier::stripe()->checkout->sessions->expire($sessionId);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
