<?php

namespace App\Actions;

use App\Contracts\StripeCheckoutGateway;
use App\Enums\OrderStatus;
use App\Models\Order;

class FulfillPaidCheckoutSession
{
    public function __construct(
        public StripeCheckoutGateway $stripeCheckout,
        public MarkOrderPaid $markOrderPaid,
    ) {}

    public function handle(Order $order, ?string $sessionId): Order
    {
        if ($order->status === OrderStatus::Paid) {
            return $order;
        }

        if ($order->status !== OrderStatus::Pending || blank($sessionId)) {
            return $order;
        }

        $session = $this->stripeCheckout->retrieveCheckoutSession($sessionId);

        if ($session === null) {
            return $order;
        }

        $paymentStatus = is_string($session->payment_status ?? null) ? $session->payment_status : '';

        if (! in_array($paymentStatus, ['paid', 'no_payment_required'], true)) {
            return $order;
        }

        if (! $this->sessionBelongsToOrder($order, $session, $sessionId)) {
            return $order;
        }

        return $this->markOrderPaid->handle($order, paymentPayload: array_filter([
            'checkout_session_id' => $session->id ?? $sessionId,
            'fulfilled_from' => 'success_url',
            'payment_intent' => is_string($session->payment_intent ?? null) ? $session->payment_intent : null,
        ]));
    }

    private function sessionBelongsToOrder(Order $order, object $session, string $sessionId): bool
    {
        $metadata = [];

        if (is_array($session->metadata ?? null)) {
            $metadata = $session->metadata;
        }

        $orderId = $metadata['order_id'] ?? $session->client_reference_id ?? null;

        if (filled($orderId) && (string) $orderId === (string) $order->id) {
            return true;
        }

        return $order->payments()
            ->where('provider_reference', $sessionId)
            ->exists();
    }
}
