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
            $session = Cashier::stripe()->checkout->sessions->retrieve($sessionId, [
                'expand' => ['payment_intent'],
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }

        $paymentIntentId = null;
        $paymentIntentStatus = null;
        $paymentIntent = $session['payment_intent'] ?? null;

        if (is_object($paymentIntent)) {
            $paymentIntentId = $paymentIntent->id ?? null;
            $paymentIntentStatus = $paymentIntent->status ?? null;
        } elseif (is_string($paymentIntent) && $paymentIntent !== '') {
            $paymentIntentId = $paymentIntent;
        }

        if (is_string($paymentIntentId) && $paymentIntentId !== '' && (! is_string($paymentIntentStatus) || $paymentIntentStatus === '')) {
            try {
                $intent = Cashier::stripe()->paymentIntents->retrieve($paymentIntentId);
                $paymentIntentStatus = $intent['status'] ?? null;
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        $orderId = $session['metadata']['order_id'] ?? null;
        $clientReferenceId = $session['client_reference_id'] ?? null;
        $url = $session['url'] ?? null;

        return [
            'id' => (string) ($session['id'] ?? ''),
            'status' => (string) ($session['status'] ?? ''),
            'url' => is_string($url) && $url !== '' ? $url : null,
            'payment_status' => (string) ($session['payment_status'] ?? ''),
            'order_id' => is_scalar($orderId) && (string) $orderId !== '' ? (string) $orderId : null,
            'client_reference_id' => is_scalar($clientReferenceId) && (string) $clientReferenceId !== '' ? (string) $clientReferenceId : null,
            'payment_intent' => is_string($paymentIntentId) && $paymentIntentId !== '' ? $paymentIntentId : null,
            'payment_intent_status' => is_string($paymentIntentStatus) && $paymentIntentStatus !== '' ? $paymentIntentStatus : null,
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
