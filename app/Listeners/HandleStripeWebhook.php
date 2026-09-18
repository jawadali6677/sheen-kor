<?php

namespace App\Listeners;

use App\Actions\FailMonetizationOrder;
use App\Actions\MarkOrderPaid;
use App\Models\Order;
use App\Models\Payment;
use Laravel\Cashier\Events\WebhookReceived;

class HandleStripeWebhook
{
    public function __construct(
        public MarkOrderPaid $markOrderPaid,
        public FailMonetizationOrder $failMonetizationOrder,
    ) {}

    public function handle(WebhookReceived $event): void
    {
        $type = $event->payload['type'] ?? '';

        match ($type) {
            'checkout.session.completed',
            'checkout.session.async_payment_succeeded' => $this->fulfillCheckout($event->payload),
            'checkout.session.expired',
            'checkout.session.async_payment_failed',
            'payment_intent.payment_failed' => $this->failCheckout($event->payload),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function fulfillCheckout(array $payload): void
    {
        $object = $payload['data']['object'] ?? [];
        $paymentStatus = $object['payment_status'] ?? '';

        if (! in_array($paymentStatus, ['paid', 'no_payment_required'], true)) {
            return;
        }

        $order = $this->orderFromPayload($payload);

        if ($order === null) {
            return;
        }

        $eventId = is_string($payload['id'] ?? null) ? $payload['id'] : null;

        if ($eventId !== null && $this->alreadyProcessed($order, $eventId)) {
            return;
        }

        $this->markOrderPaid->handle($order, paymentPayload: $this->paymentPayload($payload, $object));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function failCheckout(array $payload): void
    {
        $object = $payload['data']['object'] ?? [];
        $order = $this->orderFromPayload($payload);

        if ($order === null) {
            return;
        }

        $eventId = is_string($payload['id'] ?? null) ? $payload['id'] : null;

        if ($eventId !== null && $this->alreadyProcessed($order, $eventId)) {
            return;
        }

        $this->failMonetizationOrder->handle($order, $this->paymentPayload($payload, $object));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function orderFromPayload(array $payload): ?Order
    {
        $object = $payload['data']['object'] ?? [];
        $orderId = $object['metadata']['order_id'] ?? $object['client_reference_id'] ?? null;

        if (filled($orderId)) {
            return Order::query()->find($orderId);
        }

        $sessionId = $object['object'] === 'checkout.session' ? ($object['id'] ?? null) : null;

        if (filled($sessionId)) {
            $payment = Payment::query()->where('provider_reference', $sessionId)->first();

            return $payment?->order;
        }

        $paymentIntent = $object['payment_intent'] ?? $object['id'] ?? null;

        if (is_array($paymentIntent)) {
            $paymentIntent = $paymentIntent['id'] ?? null;
        }

        if (! is_string($paymentIntent) || $paymentIntent === '') {
            return null;
        }

        $payment = Payment::query()
            ->where(function ($query) use ($paymentIntent): void {
                $query->where('payload->payment_intent', $paymentIntent)
                    ->orWhere('provider_reference', $paymentIntent);
            })
            ->first();

        return $payment?->order;
    }

    private function alreadyProcessed(Order $order, string $eventId): bool
    {
        return $order->payments()
            ->get()
            ->contains(function (Payment $payment) use ($eventId): bool {
                $eventIds = $payment->payload['stripe_event_ids'] ?? [];

                return in_array($eventId, $eventIds, true);
            });
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $object
     * @return array<string, mixed>
     */
    private function paymentPayload(array $payload, array $object): array
    {
        $eventId = is_string($payload['id'] ?? null) ? $payload['id'] : null;
        $paymentIntent = $object['payment_intent'] ?? null;

        if (is_array($paymentIntent)) {
            $paymentIntent = $paymentIntent['id'] ?? null;
        }

        $eventIds = $eventId !== null ? [$eventId] : [];

        return array_filter([
            'stripe_event_ids' => $eventIds,
            'checkout_session_id' => ($object['object'] ?? null) === 'checkout.session' ? ($object['id'] ?? null) : null,
            'payment_intent' => is_string($paymentIntent) ? $paymentIntent : null,
        ]);
    }
}
