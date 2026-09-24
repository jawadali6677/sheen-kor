<?php

namespace App\Actions;

use App\Contracts\StripeCheckoutGateway;
use App\Enums\OrderStatus;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class StartStripeCheckout
{
    public function __construct(public StripeCheckoutGateway $stripeCheckout) {}

    public function handle(Order $order): ?string
    {
        $order->loadMissing(['user', 'payments']);

        if ($order->status === OrderStatus::Paid || $order->user === null) {
            return null;
        }

        if (! $order->shouldChargeWithStripe()) {
            return null;
        }

        $previousSessionId = $this->currentStripeSessionId($order);

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

        $attached = DB::transaction(function () use ($order, $session): bool {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== OrderStatus::Pending) {
                return false;
            }

            $this->attachCheckoutSession($locked, $session->id);

            return true;
        });

        if (! $attached) {
            $this->stripeCheckout->expireSession($session->id);

            return null;
        }

        if (is_string($previousSessionId) && $previousSessionId !== $session->id) {
            $this->stripeCheckout->expireSession($previousSessionId);
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

    private function attachCheckoutSession(Order $order, string $sessionId): Payment
    {
        $pending = $order->payments()
            ->where('status', PaymentStatus::Pending)
            ->latest('id')
            ->lockForUpdate()
            ->first();

        if ($pending instanceof Payment) {
            return $this->updatePaymentWithSession($pending, $sessionId);
        }

        return $this->createCheckoutPayment($order, $sessionId);
    }

    private function createCheckoutPayment(Order $order, string $sessionId): Payment
    {
        $attempt = $order->payments()->count() + 1;

        return $order->payments()->create([
            'provider' => PaymentProvider::Stripe,
            'provider_reference' => $sessionId,
            'idempotency_key' => Payment::checkoutIdempotencyKey($order->id, $attempt),
            'amount' => $order->amount,
            'currency' => $order->currency,
            'status' => PaymentStatus::Pending,
            'payload' => [
                'checkout_session_id' => $sessionId,
            ],
        ]);
    }

    private function updatePaymentWithSession(Payment $payment, string $sessionId): Payment
    {
        $payload = $payment->payload ?? [];
        $previous = $payment->stripeCheckoutSessionId();

        if (is_string($previous) && $previous !== $sessionId) {
            $payload['previous_checkout_session_ids'] = array_values(array_unique(array_merge(
                $payload['previous_checkout_session_ids'] ?? [],
                [$previous],
            )));
        }

        $payment->forceFill([
            'provider' => PaymentProvider::Stripe,
            'provider_reference' => $sessionId,
            'status' => PaymentStatus::Pending,
            'payload' => array_merge($payload, [
                'checkout_session_id' => $sessionId,
            ]),
        ])->save();

        return $payment;
    }

    private function currentStripeSessionId(Order $order): ?string
    {
        $payment = $order->payments()
            ->where('status', PaymentStatus::Pending)
            ->latest('id')
            ->first();

        return $payment?->stripeCheckoutSessionId();
    }
}
