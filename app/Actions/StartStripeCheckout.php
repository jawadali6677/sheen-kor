<?php

namespace App\Actions;

use App\Contracts\StripeCheckoutGateway;
use App\Enums\PaymentProvider;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;

class StartStripeCheckout
{
    public function __construct(public StripeCheckoutGateway $stripeCheckout) {}

    public function handle(Order $order): ?string
    {
        $order->loadMissing(['user', 'payments']);

        if (! $order->shouldChargeWithStripe() || $order->user === null) {
            return null;
        }

        $session = $this->stripeCheckout->createOneOffCheckout(
            $order->user,
            $order->amountInCents(),
            (string) ($order->snapshot['name'] ?? 'Sheen Kor'),
            [
                'success_url' => route('orders.show', $order).'?checkout=success&session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('orders.show', $order).'?checkout=cancelled',
                'client_reference_id' => (string) $order->id,
                'metadata' => [
                    'order_id' => (string) $order->id,
                ],
                'payment_intent_data' => [
                    'metadata' => [
                        'order_id' => (string) $order->id,
                    ],
                ],
            ],
        );

        if ($session === null || ! is_string($session->id ?? null) || $session->id === '') {
            return null;
        }

        $payment = $order->payments()->latest('id')->first();

        if ($payment instanceof Payment) {
            $payload = $payment->payload ?? [];

            $payment->forceFill([
                'provider' => PaymentProvider::Stripe,
                'provider_reference' => $session->id,
                'payload' => array_merge($payload, [
                    'checkout_session_id' => $session->id,
                ]),
            ])->save();
        }

        $url = $session->url ?? null;

        return is_string($url) && $url !== '' ? $url : null;
    }

    public function redirect(Order $order): RedirectResponse
    {
        $checkoutUrl = $this->handle($order);

        if (filled($checkoutUrl)) {
            return redirect()->away($checkoutUrl);
        }

        if ($order->shouldChargeWithStripe()) {
            return redirect()
                ->route('orders.show', $order)
                ->with('error', 'Checkout could not be started. You can retry payment from the order page, or an admin can mark it paid.');
        }

        return redirect()
            ->route('orders.show', $order)
            ->with('success', 'Order saved as pending. An admin will mark it paid for testing until a payment provider is connected.');
    }
}
