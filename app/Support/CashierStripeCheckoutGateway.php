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

    public function retrieveCheckoutSession(string $sessionId): ?object
    {
        try {
            $session = Cashier::stripe()->checkout->sessions->retrieve($sessionId);
            $metadata = $session->metadata ?? [];

            if (is_object($metadata) && method_exists($metadata, 'toArray')) {
                $metadata = $metadata->toArray();
            }

            $paymentIntent = $session->payment_intent ?? null;

            if (is_object($paymentIntent)) {
                $paymentIntent = $paymentIntent->id ?? null;
            }

            return (object) [
                'id' => $session->id,
                'payment_status' => $session->payment_status,
                'metadata' => is_array($metadata) ? $metadata : (array) $metadata,
                'client_reference_id' => $session->client_reference_id,
                'payment_intent' => is_string($paymentIntent) ? $paymentIntent : null,
            ];
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }
}
