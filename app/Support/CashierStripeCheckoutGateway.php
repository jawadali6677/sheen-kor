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

    public function retrieveCheckoutSession(string $sessionId): ?array
    {
        try {
            $session = Cashier::stripe()->checkout->sessions->retrieve($sessionId);
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }

        $paymentIntent = $session['payment_intent'] ?? null;

        if (is_object($paymentIntent)) {
            $paymentIntent = $paymentIntent->id ?? null;
        }

        $orderId = $session['metadata']['order_id'] ?? null;
        $clientReferenceId = $session['client_reference_id'] ?? null;

        return [
            'id' => (string) ($session['id'] ?? ''),
            'payment_status' => (string) ($session['payment_status'] ?? ''),
            'order_id' => is_scalar($orderId) && (string) $orderId !== '' ? (string) $orderId : null,
            'client_reference_id' => is_scalar($clientReferenceId) && (string) $clientReferenceId !== '' ? (string) $clientReferenceId : null,
            'payment_intent' => is_string($paymentIntent) && $paymentIntent !== '' ? $paymentIntent : null,
        ];
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
